<?php

namespace App\Service;

use App\Entity\Bucket;
use App\Entity\LifecycleRules;
use App\Exception\Lifecycle\InvalidLifecycleRuleException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LifecycleService
{
    public function __construct(
        private BucketService $bucketService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function run(Bucket $bucket, int $mpuHours, int $ncvHours, int $cvHours, mixed $io): void
    {
        if ($mpuHours >= 0) {
            $this->deleteMultipartUploads($bucket, $mpuHours, $io);
        }

        if ($ncvHours >= 0) {
            $this->deleteNonCurrentVersions($bucket, $ncvHours, $io);
        }

        if ($cvHours >= 0) {
            $this->deleteCurrentVersions($bucket, $cvHours, $io);
        }
    }

    public function deleteMultipartUploads(Bucket $bucket, int $mpuHours, mixed $io): void
    {
        $expireDate = new \DateTime($mpuHours.' hours ago');
        if ($io instanceof OutputInterface) {
            $io->writeln('Deleting multi-part uploads older than '.$expireDate->format('Y-m-d H:i:s').' from bucket '.$bucket->getName());
        }
        $keyMarker = '';
        $uploadIdMarker = '';
        do {
            $objects = $this->bucketService->listMultipartUploads($bucket, '', '', $keyMarker, $uploadIdMarker, 1000);
            foreach ($objects->getFiles() as $file) {
                if ($file->getMtime() < $expireDate) {
                    // clean up multipart upload
                    try {
                        $this->bucketService->deleteFileVersion($file, true, true);
                        if ($io instanceof OutputInterface) {
                            $io->writeln('Deleted '.$file->getMultipartUploadId().' '.$file->getName());
                        }
                    } catch (\Exception $e) {
                        if ($io instanceof OutputInterface) {
                            $io->writeln('Unable to delete '.$file->getMultipartUploadId().' '.$file->getName().': '.$e->getMessage());
                        }
                    }
                }
            }
            if ($objects->isTruncated()) {
                $keyMarker = $objects->getNextMarker();
                $uploadIdMarker = $objects->getNextMarker2();
            } else {
                $keyMarker = '';
                $uploadIdMarker = '';
            }
        } while ('' != $keyMarker);
    }

    public function deleteCurrentVersions(Bucket $bucket, int $cvHours, mixed $io): void
    {
        $expireDate = new \DateTime($cvHours.' hours ago');
        if ($io instanceof OutputInterface) {
            $io->writeln('Deleting current versions older than '.$expireDate->format('Y-m-d H:i:s').' from bucket '.$bucket->getName());
        }
        $keyMarker = '';
        $versionMarker = '';
        do {
            $objects = $this->bucketService->listFileVersions($bucket, '', '', $keyMarker, $versionMarker, 1000);
            foreach ($objects->getFiles() as $file) {
                if ($file->isCurrentVersion() && $file->getMtime() < $expireDate) {
                    // delete file
                    try {
                        $this->bucketService->deleteFile($file, true, true);
                        if ($io instanceof OutputInterface) {
                            $io->writeln('Deleted '.($file->getVersion() ?? 'NULL').' '.$file->getName());
                        }
                    } catch (\Exception $e) {
                        if ($io instanceof OutputInterface) {
                            $io->writeln('Unable to delete '.($file->getVersion() ?? 'NULL').' '.$file->getName().': '.$e->getMessage());
                        }
                    }
                }
            }
            if ($objects->isTruncated()) {
                $keyMarker = $objects->getNextMarker();
                $versionMarker = $objects->getNextMarker2();
            } else {
                $keyMarker = '';
                $versionMarker = '';
            }
        } while ('' != $keyMarker);
    }

    public function deleteNonCurrentVersions(Bucket $bucket, int $ncvHours, mixed $io): void
    {
        $expireDate = new \DateTime($ncvHours.' hours ago');
        if ($io instanceof OutputInterface) {
            $io->writeln('Deleting non-current versions older than '.$expireDate->format('Y-m-d H:i:s').' from bucket '.$bucket->getName());
        }
        $keyMarker = '';
        $versionMarker = '';
        do {
            $objects = $this->bucketService->listFileVersions($bucket, '', '', $keyMarker, $versionMarker, 1000);
            foreach ($objects->getFiles() as $file) {
                if (!$file->isCurrentVersion() && $file->getMtime() < $expireDate) {
                    // delete file
                    try {
                        $this->bucketService->deleteFileVersion($file, true, true);
                        if ($io instanceof OutputInterface) {
                            $io->writeln('Deleted '.($file->getVersion() ?? 'NULL').' '.$file->getName());
                        }
                    } catch (\Exception $e) {
                        if ($io instanceof OutputInterface) {
                            $io->writeln('Unable to delete '.($file->getVersion() ?? 'NULL').' '.$file->getName().': '.$e->getMessage());
                        }
                    }
                }
            }
            if ($objects->isTruncated()) {
                $keyMarker = $objects->getNextMarker();
                $versionMarker = $objects->getNextMarker2();
            } else {
                $keyMarker = '';
                $versionMarker = '';
            }
        } while ('' != $keyMarker);
    }

    /**
     * @return mixed[]
     */
    public function parseLifecycleRules(string $rules): array
    {
        $xml = simplexml_load_string($rules);
        if ('LifecycleConfiguration' != $xml->getName()) {
            throw new \Exception('Invalid lifecycle configuration');
        }

        $rules = [];
        foreach ($xml->Rule as $rule) {
            $rules[] = $this->parseLifecycleRule($rule);
        }

        return $rules;
    }

    /**
     * @param \SimpleXMLElement $rule
     *
     * @return mixed[]
     */
    public function parseLifecycleRule(mixed $rule): array
    {
        $parsedRule = [];
        if (!isset($rule->Status) || ('Enabled' !== (string) $rule->Status && 'Disabled' !== (string) $rule->Status)) {
            throw new InvalidLifecycleRuleException('Invalid Status in Rule element');
        }

        if (isset($rule->ID)) {
            $parsedRule['id'] = (string) $rule->ID;
            if (strlen($parsedRule['id']) < 1 || strlen($parsedRule['id']) > 255) {
                throw new InvalidLifecycleRuleException('Invalid ID in Rule element');
            }
        } else {
            $parsedRule['id'] = null;
        }

        if (isset($rule->AbortIncompleteMultipartUpload)) {
            if (isset($rule->AbortIncompleteMultipartUpload->DaysAfterInitiation) && is_numeric((string) $rule->AbortIncompleteMultipartUpload->DaysAfterInitiation)) {
                $days = (int) $rule->AbortIncompleteMultipartUpload->DaysAfterInitiation;
                if ($days > 0) {
                    $parsedRule['abortmpu'] = $days;
                } else {
                    throw new InvalidLifecycleRuleException('Invalid DaysAfterInitiation in AbortIncompleteMultipartUpload element');
                }
            } else {
                throw new InvalidLifecycleRuleException('Invalid DaysAfterInitiation in AbortIncompleteMultipartUpload element');
            }
        } else {
            $parsedRule['abortmpu'] = null;
        }

        if (isset($rule->Expiration)) {
            if (isset($rule->Expiration->Date)) {
                $date = \DateTime::createFromFormat(\DateTime::ATOM, (string) $rule->Expiration->Date, new \DateTimeZone('UTC'));
                $parsedRule['expiration_date'] = $date;
            } else {
                $parsedRule['expiration_date'] = null;
            }

            if (isset($rule->Expiration->Days)) {
                if (is_numeric((string) $rule->Expiration->Days)) {
                    $days = (int) $rule->Expiration->Days;
                    if ($days > 0) {
                        $parsedRule['expiration_days'] = $days;
                    } else {
                        throw new InvalidLifecycleRuleException('Invalid Days in Expiration element');
                    }
                } else {
                    throw new InvalidLifecycleRuleException('Invalid Days in Expiration element');
                }
            } else {
                $parsedRule['expiration_days'] = null;
            }

            if (isset($rule->Expiration->ExpiredObjectDeleteMarker)) {
                if ('true' === (string) $rule->Expiration->ExpiredObjectDeleteMarker) {
                    $parsedRule['expiration_delete_marker'] = true;
                } else {
                    $parsedRule['expiration_delete_marker'] = false;
                }
            } else {
                $parsedRule['expiration_delete_marker'] = null;
            }
        }

        if (isset($rule->NoncurrentVersionExpiration)) {
            if (isset($rule->NoncurrentVersionExpiration->NoncurrentDays)) {
                if (is_numeric((string) $rule->NoncurrentVersionExpiration->NoncurrentDays)) {
                    $days = (int) $rule->NoncurrentVersionExpiration->NoncurrentDays;
                    if ($days > 0) {
                        $parsedRule['noncurrent_days'] = $days;
                    } else {
                        throw new InvalidLifecycleRuleException('Invalid NoncurrentDays in NoncurrentVersionExpiration element');
                    }
                } else {
                    throw new InvalidLifecycleRuleException('Invalid NoncurrentDays in NoncurrentVersionExpiration element');
                }
            } else {
                $parsedRule['noncurrent_days'] = null;
            }

            if (isset($rule->NoncurrentVersionExpiration->NewerNoncurrentVersions)) {
                if (is_numeric((string) $rule->NoncurrentVersionExpiration->NewerNoncurrentVersions)) {
                    $versions = (int) $rule->NoncurrentVersionExpiration->NewerNoncurrentVersions;
                    if ($versions > 0 && $versions < 100) {
                        $parsedRule['noncurrent_newer_versions'] = $versions;
                    } else {
                        throw new InvalidLifecycleRuleException('Invalid NewerNoncurrentVersions in NoncurrentVersionExpiration element');
                    }
                } else {
                    throw new InvalidLifecycleRuleException('Invalid NewerNoncurrentVersions in NoncurrentVersionExpiration element');
                }
            } else {
                $parsedRule['noncurrent_newer_versions'] = null;
            }
        } else {
            $parsedRule['noncurrent_days'] = null;
            $parsedRule['noncurrent_newer_versions'] = null;
        }

        /*
         * Not supported yet, ignore for now
         *   NoncurrentVersionTransitions
         *   Transitions
         */

        // Filters
        if (isset($rule->Filter)) {
            $parsedRule['filter'] = $this->parseLifecycleFilter($rule->Filter);
        } else {
            $parsedRule['filter'] = null;
        }

        return $parsedRule;
    }

    /**
     * @param \SimpleXMLElement $filter
     *
     * @return mixed[]
     */
    public function parseLifecycleFilter(mixed $filter, bool $and = false): array
    {
        $parsedFilter = [];
        if (isset($filter->Prefix)) {
            $parsedFilter['prefix'] = (string) $filter->Prefix;
        } else {
            $parsedFilter['prefix'] = null;
        }
        if ($filter->ObjectSizeGreaterThan) {
            $parsedFilter['size_greater'] = (int) $filter->ObjectSizeGreaterThan;
        } else {
            $parsedFilter['size_greater'] = null;
        }
        if ($filter->ObjectSizeLessThan) {
            $parsedFilter['size_less'] = (int) $filter->ObjectSizeLessThan;
        } else {
            $parsedFilter['size_less'] = null;
        }

        if ($and) {
            // we are in an And block, Tag can be multiple elements
            if (isset($filter->Tag)) {
                $parsedFilter['tags'] = [];
                foreach ($filter->Tag as $tag) {
                    $parsedFilter['tags'][] = ['key' => (string) $tag->Key, 'value' => (string) $tag->Value];
                }
            } else {
                $parsedFilter['tags'] = null;
            }
        } else {
            // we are in a rule, Tag is a single tag, and we need to parse And if present
            if (isset($filter->Tag->Key) && isset($filter->Tag->Value)) {
                $parsedFilter['tag'] = ['key' => (string) $filter->Tag->Key, 'value' => (string) $filter->Tag->Value];
            } else {
                $parsedFilter['tag'] = null;
            }

            if (isset($filter->And)) {
                $parsedFilter['and'] = $this->parseLifecycleFilter($filter->And, true);
            } else {
                $parsedFilter['and'] = null;
            }

            // only one of Prefix, Tag, ObjectSizeGreaterThan, ObjectSizeLessThan, And is supported
            if (count(array_filter($parsedFilter)) > 1 || count(array_filter($parsedFilter)) == 0) {
                throw new InvalidLifecycleRuleException('Only one of Prefix, Tag, ObjectSizeGreaterThan, ObjectSizeLessThan, And is supported');
            }
        }

        return $parsedFilter;
    }

    /**
     * Entity operations.
     */
    public function setBucketLifecycleRules(Bucket $bucket, string $xml, bool $flush = false): LifecycleRules
    {
        // not using parsed result here, just to make sure it's valid
        $rules = $this->parseLifecycleRules($xml);

        foreach ($bucket->getLifecycleRules() as $rule) {
            $this->entityManager->remove($rule);
        }

        $lifecycleRules = new LifecycleRules($bucket, $xml);
        $this->saveLifecycleRules($lifecycleRules, $flush);

        return $lifecycleRules;
    }

    public function saveLifecycleRules(LifecycleRules $lifecycleRules, bool $flush = false): void
    {
        $this->entityManager->persist($lifecycleRules);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function deleteLifecycleRules(LifecycleRules $lifecycleRules, bool $flush = false): void
    {
        $this->entityManager->remove($lifecycleRules);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function filterRulesArray(array $array): array
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = $this->filterRulesArray($value);
                // Remove empty arrays after filtering
                if (empty($value)) {
                    unset($array[$key]);
                }
            } else {
                // Remove null values
                if (null === $value) {
                    unset($array[$key]);
                }
            }
        }

        return $array;
    }
}
