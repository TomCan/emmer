<?php

namespace App\Service;

use App\Domain\Lifecycle\ParsedLifecycleRule;
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
     * @return ParsedLifecycleRule[]
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

    public function parseLifecycleRule(\SimpleXMLElement $rule): ParsedLifecycleRule
    {
        $parsedRule = new ParsedLifecycleRule();

        if (!isset($rule->Status) || ('Enabled' !== (string) $rule->Status && 'Disabled' !== (string) $rule->Status)) {
            throw new InvalidLifecycleRuleException('Invalid Status in Rule element');
        } else {
            $parsedRule->setStatus((string) $rule->Status);
        }

        if (isset($rule->ID)) {
            $parsedRule->setId((string) $rule->ID);
            if ('' == $parsedRule->getId() || strlen($parsedRule->getId()) > 255) {
                throw new InvalidLifecycleRuleException('Invalid ID in Rule element');
            }
        }

        if (isset($rule->AbortIncompleteMultipartUpload)) {
            if (isset($rule->AbortIncompleteMultipartUpload->DaysAfterInitiation) && is_numeric((string) $rule->AbortIncompleteMultipartUpload->DaysAfterInitiation)) {
                $days = (int) $rule->AbortIncompleteMultipartUpload->DaysAfterInitiation;
                if ($days > 0) {
                    $parsedRule->setAbortIncompleteMultipartUploadDays($days);
                } else {
                    throw new InvalidLifecycleRuleException('Invalid DaysAfterInitiation in AbortIncompleteMultipartUpload element');
                }
            } else {
                throw new InvalidLifecycleRuleException('Invalid DaysAfterInitiation in AbortIncompleteMultipartUpload element');
            }
        }

        if (isset($rule->Expiration)) {
            if (isset($rule->Expiration->Date)) {
                try {
                    $date = \DateTime::createFromFormat(\DateTime::ATOM, (string) $rule->Expiration->Date, new \DateTimeZone('UTC'));
                    $parsedRule->setExpirationDate($date);
                } catch (\Exception $e) {
                    throw new InvalidLifecycleRuleException('Invalid Date in Expiration element');
                }
            }

            if (isset($rule->Expiration->Days)) {
                if (is_numeric((string) $rule->Expiration->Days)) {
                    $days = (int) $rule->Expiration->Days;
                    if ($days > 0) {
                        $parsedRule->setExpirationDays($days);
                    } else {
                        throw new InvalidLifecycleRuleException('Invalid Days in Expiration element');
                    }
                } else {
                    throw new InvalidLifecycleRuleException('Invalid Days in Expiration element');
                }
            }

            if (isset($rule->Expiration->ExpiredObjectDeleteMarker)) {
                if ('true' === (string) $rule->Expiration->ExpiredObjectDeleteMarker) {
                    $parsedRule->setExpiredObjectDeleteMarker(true);
                } else {
                    $parsedRule->setExpiredObjectDeleteMarker(false);
                }
            }
        }

        if (isset($rule->NoncurrentVersionExpiration)) {
            if (isset($rule->NoncurrentVersionExpiration->NoncurrentDays)) {
                if (is_numeric((string) $rule->NoncurrentVersionExpiration->NoncurrentDays)) {
                    $days = (int) $rule->NoncurrentVersionExpiration->NoncurrentDays;
                    if ($days > 0) {
                        $parsedRule->setNoncurrentVersionExpirationDays($days);
                    } else {
                        throw new InvalidLifecycleRuleException('Invalid NoncurrentDays in NoncurrentVersionExpiration element');
                    }
                } else {
                    throw new InvalidLifecycleRuleException('Invalid NoncurrentDays in NoncurrentVersionExpiration element');
                }
            }

            if (isset($rule->NoncurrentVersionExpiration->NewerNoncurrentVersions)) {
                if (is_numeric((string) $rule->NoncurrentVersionExpiration->NewerNoncurrentVersions)) {
                    $versions = (int) $rule->NoncurrentVersionExpiration->NewerNoncurrentVersions;
                    if ($versions > 0 && $versions < 100) {
                        $parsedRule->setNoncurrentVersionNewerVersions($versions);
                    } else {
                        throw new InvalidLifecycleRuleException('Invalid NewerNoncurrentVersions in NoncurrentVersionExpiration element');
                    }
                } else {
                    throw new InvalidLifecycleRuleException('Invalid NewerNoncurrentVersions in NoncurrentVersionExpiration element');
                }
            }
        }

        /*
         * Not supported yet, ignore for now
         *   NoncurrentVersionTransitions
         *   Transitions
         */

        // Filters
        if (isset($rule->Filter)) {
            $this->parseLifecycleFilter($parsedRule, $rule->Filter, false);
        }

        return $parsedRule;
    }

    /**
     * @param \SimpleXMLElement $filter
     */
    public function parseLifecycleFilter(ParsedLifecycleRule $parsedRule, mixed $filter, bool $inAnd = false): void
    {
        if (isset($filter->Prefix)) {
            if ($inAnd) {
                $parsedRule->setFilterAndPrefix((string) $filter->Prefix);
            } else {
                $parsedRule->setFilterPrefix((string) $filter->Prefix);
            }
        }
        if ($filter->ObjectSizeGreaterThan) {
            if ($inAnd) {
                $parsedRule->setFilterAndSizeGreaterThan((int) $filter->ObjectSizeGreaterThan);
            } else {
                $parsedRule->setFilterSizeGreaterThan((int) $filter->ObjectSizeGreaterThan);
            }
        }
        if ($filter->ObjectSizeLessThan) {
            if ($inAnd) {
                $parsedRule->setFilterAndSizeLessThan((int) $filter->ObjectSizeLessThan);
            } else {
                $parsedRule->setFilterSizeLessThan((int) $filter->ObjectSizeLessThan);
            }
        }

        if ($inAnd) {
            // we are in an And block, Tag can be multiple elements
            if (isset($filter->Tag)) {
                $tags = [];
                foreach ($filter->Tag as $tag) {
                    $tags[] = ['Key' => (string) $tag->Key, 'Value' => (string) $tag->Value];
                }
                $parsedRule->setFilterAndTags($tags);
            }
        } else {
            // we are in a rule, Tag can be a single tag
            if (isset($filter->Tag->Key) && isset($filter->Tag->Value)) {
                $tag = ['Key' => (string) $filter->Tag->Key, 'Value' => (string) $filter->Tag->Value];
                $parsedRule->setFilterTag($tag);
            }

            // parse nested And block
            if (isset($filter->And)) {
                $this->parseLifecycleFilter($parsedRule, $filter->And, true);
            }

            // only one of Prefix, Tag, ObjectSizeGreaterThan, ObjectSizeLessThan, And is supported
            $nonNull = (null != $parsedRule->getFilterPrefix() ? 1 : 0) +
                        (null != $parsedRule->getFilterSizeGreaterThan() ? 1 : 0) +
                        (null != $parsedRule->getFilterSizeLessThan() ? 1 : 0) +
                        (null != $parsedRule->getFilterTag() ? 1 : 0) +
                        ($parsedRule->hasAnd() ? 1 : 0);
            if ($nonNull > 1) {
                throw new InvalidLifecycleRuleException('Only one of Prefix, Tag, ObjectSizeGreaterThan, ObjectSizeLessThan, And is supported');
            }
        }
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

    /**
     * @param ParsedLifecycleRule[] $parsedRules
     *
     * @return mixed[]
     */
    public function parsedRulesToXmlArray(array $parsedRules): array
    {
        $results = [];
        foreach ($parsedRules as $parsedRule) {
            // compose full array
            $result = [
                'ID' => $parsedRule->getId(),
                'Status' => $parsedRule->getStatus(),
                'AbortIncompleteMultipartUpload' => [
                    'DaysAfterInitiation' => $parsedRule->getAbortIncompleteMultipartUploadDays(),
                ],
                'Expiration' => [
                    'Date' => $parsedRule->getExpirationDate() ? $parsedRule->getExpirationDate()->format(\DateTime::ATOM) : null,
                    'Days' => $parsedRule->getExpirationDays(),
                    'ExpiredObjectDeleteMarker' => null == $parsedRule->getExpiredObjectDeleteMarker() ? null : ($parsedRule->getExpiredObjectDeleteMarker() ? 'true' : 'false'),
                ],
                'NoncurrentVersionExpiration' => [
                    'NoncurrentDays' => $parsedRule->getNoncurrentVersionExpirationDays(),
                    'NewerNoncurrentVersions' => $parsedRule->getNoncurrentVersionNewerVersions(),
                ],
                'Filter' => [
                    'Prefix' => $parsedRule->getFilterPrefix(),
                    'ObjectSizeGreaterThan' => $parsedRule->getFilterSizeGreaterThan(),
                    'ObjectSizeLessThan' => $parsedRule->getFilterSizeLessThan(),
                    'Tag' => $parsedRule->getFilterTag(),
                    'And' => [
                        'Prefix' => $parsedRule->getFilterAndPrefix(),
                        'ObjectSizeGreaterThan' => $parsedRule->getFilterAndSizeGreaterThan(),
                        'ObjectSizeLessThan' => $parsedRule->getFilterAndSizeLessThan(),
                        '#Tag' => $parsedRule->getFilterAndTags(),
                    ],
                ],
            ];

            // filter out null values of each subarray
            $result['AbortIncompleteMultipartUpload'] = array_filter($result['AbortIncompleteMultipartUpload']);
            $result['Expiration'] = array_filter($result['Expiration']);
            $result['NoncurrentVersionExpiration'] = array_filter($result['NoncurrentVersionExpiration']);
            $result['Filter']['And'] = array_filter($result['Filter']['And']);
            $result['Filter'] = array_filter($result['Filter']);
            $result = array_filter($result);

            $results[] = $result;
        }

        return $results;
    }
}
