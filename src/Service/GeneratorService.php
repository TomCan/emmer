<?php

namespace App\Service;

class GeneratorService
{
    public const CLASS_LOWER = 1;
    public const CLASS_UPPER = 2;
    public const CLASS_NUMBER = 4;
    public const CLASS_HEX = 8;

    private const CLASS_DEF = [
        [
            'match' => self::CLASS_LOWER,
            'start' => 97, // ord('a');
            'length' => 26,
        ],
        [
            'match' => self::CLASS_UPPER,
            'start' => 65, // ord('A');
            'length' => 26,
        ],
        [
            'match' => self::CLASS_NUMBER,
            'start' => 48, // ord('0');
            'length' => 10,
        ],
        [
            'match' => self::CLASS_HEX,
            'start' => 97, // ord('a');
            'length' => 6,
        ],
        [
            'match' => self::CLASS_HEX,
            'start' => 48, // ord('0');
            'length' => 10,
        ],
    ];

    public function generateId(int $length, int $classes = 7): string
    {
        $id = '';

        $max = 0;
        foreach (self::CLASS_DEF as $class) {
            if ($classes & $class['match']) {
                $max += $class['length'];
            }
        }

        for ($i = 0; $i < $length; $i++) {
            $char = rand(0, $max - 1);
            foreach (self::CLASS_DEF as $class) {
                if ($classes & $class['match']) {
                    if ($char < $class['length']) {
                        $id .= chr($class['start'] + $char);
                        break;
                    } else {
                        $char -= $class['length'];
                    }
                }
            }
        }

        return $id;
    }

    public function generateIdGroup(string $delimiter = '-', mixed $groups = 1, int $length = 8, int $classes = 7): string
    {
        $parts = [];
        if (is_array($groups)) {
            foreach ($groups as $group) {
                $l = (int)($group['length'] ?? $length) ?: $length;
                $c = (int)($group['classes'] ?? $classes) ?: $classes;
                $parts[] = $this->generateId($l, $c);
            }
        } elseif (is_int($groups)) {
            // create $groups number of parts of equal length
            for ($i = 0; $i < $groups; $i++) {
                $parts[] = $this->generateId($length, $classes);
            }
        } else {
            throw new \InvalidArgumentException('Groups must be either an array or an integer');
        }

        return implode($delimiter, $parts);
    }
}
