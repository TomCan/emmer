<?php

namespace App\Service;

use App\Entity\Bucket;
use App\Entity\User;

class PolicyResolver
{
    /**
     * @return array<array{Sid: string, Effect: string, Principal: string[], Action: string[], Resource: string[]}>
     */
    public function convertPolicies(mixed ...$policyCollections): array
    {
        $convertedPolicies = [];
        foreach ($policyCollections as $policyCollection) {
            if (is_string($policyCollection)) {
                $policyText = (string) $policyCollection;
                $statements = $this->convertToStatements($policyText);
                if (null !== $statements) {
                    $convertedPolicies = array_merge($convertedPolicies, $statements);
                }
            } elseif ($policyCollection instanceof Bucket) {
                /*
                 * Bucket policies only apply to the bucket itself, or objects within the bucket.
                 * We need to filter out any policies that apply to other resources.
                 */
                foreach ($policyCollection->getPolicies() as $policy) {
                    $statements = $this->convertToStatements($policy->getPolicy());
                    if (null !== $statements) {
                        // filter out resources that do not refer to the current bucket
                        foreach ($statements as $statement) {
                            $filteredResources = [];
                            foreach ($statement['Resource'] as $resource) {
                                if ($resource == 'emr:bucket:'.$policyCollection->getName() || str_starts_with($resource, 'emr:bucket:'.$policyCollection->getName().'/')) {
                                    $filteredResources[] = $resource;
                                }
                            }
                            // Don't add it there are no resources left
                            if (count($filteredResources) > 0) {
                                $statement['Resource'] = $filteredResources;
                                $convertedPolicies[] = $statement;
                            }
                        }
                    }
                }
            } elseif ($policyCollection instanceof User) {
                // user policies
                foreach ($policyCollection->getPolicies() as $policy) {
                    $statements = $this->convertToStatements($policy->getPolicy());
                    if (null !== $statements) {
                        // Force principal to be the user.
                        foreach ($statements as $statement) {
                            $statement['Principal'] = ['emr:user:'.$policyCollection->getEmail()];
                            $convertedPolicies[] = $statement;
                        }
                    }
                }
            }
        }

        return $convertedPolicies;
    }

    /**
     * @return array<array{Sid: string, Effect: string, Principal: string[], Action: string[], Resource: string[]}>|null
     */
    private function convertToStatements(string $policyText): ?array
    {
        $parsedStatements = [];
        $policy = json_decode($policyText, true);
        if (!isset($policy['Statement']) || !is_array($policy['Statement'])) {
            // does not appear to be a valid policy, ignore it.
            return null;
        }
        if (isset($policy['Statement'][0])) {
            // array of statements
            foreach ($policy['Statement'] as $statement) {
                $statement = $this->validateStatement($statement);
                if (null !== $statement) {
                    $parsedStatements[] = $statement;
                }
            }
        } else {
            // single policy
            $statement = $this->validateStatement($policy['Statement']);
            if (null !== $statement) {
                $parsedStatements[] = $statement;
            }
        }

        return $parsedStatements;
    }

    /**
     * @param mixed[] $statement
     *
     * @return array{Sid: string, Effect: string, Principal: string[], Action: string[], Resource: string[]}|null
     */
    private function validateStatement(array $statement): ?array
    {
        // remove unknown / unsupported keys
        $statement = array_filter($statement, function ($key) { return in_array($key, ['Sid', 'Effect', 'Principal', 'Action', 'Resource']); }, ARRAY_FILTER_USE_KEY);

        // effect must be present, and be one of Allow/Deny
        if (!isset($statement['Effect']) || ('Allow' !== $statement['Effect'] && 'Deny' !== $statement['Effect'])) {
            return null;
        }

        // flatten action
        if (!isset($statement['Action'])) {
            // must have action, for now not checking on valid actions
            return null;
        } else {
            $actions = [];
            if (is_string($statement['Action'])) {
                $actions[] = $statement['Action'];
            } else {
                foreach ($statement['Action'] as $action) {
                    if (is_string($action)) {
                        $actions[] = $action;
                    }
                }
            }
            if (count($actions)) {
                $statement['Action'] = $actions;
            } else {
                return null;
            }
        }


        // flatten principals
        if (!isset($statement['Principal'])) {
            $statement['Principal'] = [];
        } else {
            $principals = [];
            if (is_string($statement['Principal'])) {
                $principals[] = $statement['Principal'];
            } else {
                foreach ($statement['Principal'] as $key => $principal) {
                    if (is_array($principal)) {
                        foreach ($principal as $principalValue) {
                            if (is_string($principalValue)) {
                                $principals[] = $key.':'.$principalValue;
                            } else {
                                // should be string
                                return null;
                            }
                        }
                    } elseif (is_string($principal)) {
                        if (is_int($key)) {
                            // no key, so just add the principal
                            $principals[] = $principal;
                        } else {
                            // string key, prepend principal
                            $principals[] = $key.':'.$principal;
                        }
                    } else {
                        // invalid principal
                        return null;
                    }
                }
            }
            $statement['Principal'] = $principals;
        }

        // flatten resources
        if (!isset($statement['Resource'])) {
            $statement['Resource'] = [];
        } else {
            $resources = [];
            if (is_string($statement['Resource'])) {
                $resources[] = $statement['Resource'];
            } elseif (is_array($statement['Resource'])) {
                foreach ($statement['Resource'] as $resource) {
                    if (is_string($resource)) {
                        $resources[] = $resource;
                    } else {
                        // invalid resource
                        return null;
                    }
                }
            } else {
                // invalid resource
                return null;
            }
            $statement['Resource'] = $resources;
        }

        return $statement;
    }

    /**
     * @param array<array{Sid: string, Effect: string, Principal: string[], Action: string[], Resource: string[]}> $statements
     */
    public function isCallPermitted(array $statements, string $principal, string $action, string $resource): bool
    {
        $permitted = false;
        foreach ($statements as $statement) {
            $result = $this->evaluateStatement($statement, $principal, $action, $resource);
            if (1 === $result) {
                // statement matches and allows. Still process other statements in case of denies.
                $permitted = true;
            } elseif (-1 === $result) {
                // statement matches and denies. No need to continue.
                return false;
            }
        }

        return $permitted;
    }

    /**
     * Check if statement is applicable to the given principal, action and resource.
     * If the statement is not applicable, return 0
     * If the statement is applicable and allows, return 1
     * If the statement is applicable and denies, return -1.
     *
     * @param array{Sid: string, Effect: string, Principal: string[], Action: string[], Resource: string[]} $statement
     */
    private function evaluateStatement(array $statement, string $principal, string $action, string $resource): int
    {
        $matches = false;
        foreach ($statement['Principal'] as $statementPrincipal) {
            if ($statementPrincipal == $principal) {
                $matches = true;
                break;
            }
        }
        if (!$matches) {
            return 0;
        }

        $matches = false;
        foreach ($statement['Action'] as $statementAction) {
            if ($this->isValueMatching($statementAction, $action)) {
                $matches = true;
                break;
            }
        }
        if (!$matches) {
            return 0;
        }

        $matches = false;
        foreach ($statement['Resource'] as $statementResource) {
            if ($this->isValueMatching($statementResource, $resource)) {
                $matches = true;
                break;
            }
        }
        if (!$matches) {
            return 0;
        }

        // Principal, Action and Resource all match, so statement is applicable
        if ('Allow' === $statement['Effect']) {
            return 1;
        } else {
            return -1;
        }
    }

    private function isValueMatching(string $pattern, string $against): bool
    {
        // escape input string
        $pattern = preg_quote($pattern, '#');
        // unescape * and ? and replace with regex equivalents, and add start/stop anchors and delimiters.
        $pattern = '#^'.str_replace(['\*', '\?'], ['.*', '.'], $pattern).'$#';

        // add start and end anchors, and delimiters
        return (bool) preg_match($pattern, $against);
    }
}
