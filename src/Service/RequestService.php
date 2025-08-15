<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

class RequestService
{
    public function evaluateConditionalHeaders(Request $request, string $etagValue, \DateTime $objectTime): int
    {
        // if-match + if-unmodified-since
        if ($request->headers->has('if-match') && $request->headers->has('if-unmodified-since')) {
            // match + not unmodified = serve
            if ($this->isIfMatch($request, $etagValue) && !$this->isUnmodifiedSince($request, $objectTime)) {
                return 200;
            }
        }

        // if-none-match + if-modified-since
        if ($request->headers->has('if-none-match') && $request->headers->has('if-modified-since')) {
            // !none-match + modified = serve
            if (!$this->isIfNoneMatch($request, $etagValue) && $this->isModifiedSince($request, $objectTime)) {
                return 304;
            }
        }

        // if-match
        if ($request->headers->has('if-match')) {
            if ($this->etagHeaderMatches($request->headers->get('if-match'), $etagValue)) {
                return 200;
            } else {
                return 412;
            }
        }

        // if-none-match
        if ($request->headers->has('if-none-match')) {
            if ($this->etagHeaderMatches($request->headers->get('if-none-match'), $etagValue)) {
                return 304;
            } else {
                return 200;
            }
        }

        // if-modified-since
        if ($request->headers->has('if-modified-since')) {
            if ($objectTime > new \DateTime($request->headers->get('if-modified-since'))) {
                return 200;
            } else {
                return 304;
            }
        }

        // if-unmodified-since
        if ($request->headers->has('if-unmodified-since')) {
            if ($objectTime < new \DateTime($request->headers->get('if-unmodified-since'))) {
                return 200;
            } else {
                return 412;
            }
        }

        // no conditional headers
        return 200;
    }

    private function etagHeaderMatches($headerValue, $etagValue) {
        $values = explode(',', $headerValue);
        foreach ($values as $value) {
            $value = trim($value);
            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                // stip quotes
                $value = substr($value, 1, -1);
            }
            if ('*' == $value || $etagValue == $value) {
                // matches or wildcard
                return true;
            }
        }

        // no match found
        return false;
    }

    public function getRange(string $headerValue, int $max): array
    {
        // only use first range
        $ranges = explode(',', $headerValue);
        $range = explode('-', $ranges[0]);
        $range = array_map('trim', $range);
        if (str_starts_with($range[0], 'bytes=')) {
            $range[0] = substr($range[0], 6);
        } else {
            throw new \InvalidArgumentException('Invalid Range Unit');
        }

        // must be either int or empty
        if (
            $range[0] !== '' && !preg_match('/^[0-9]+$/', $range[0])
            || $range[1] !== '' && !preg_match('/^[0-9]+$/', $range[0])
        ) {
            throw new \InvalidArgumentException('Invalid Range');
        }

        if ($range[0] == '' && $range[1] == '') {
            // can't both be empty
            throw new \InvalidArgumentException('Invalid Range');
        }

        if ($range[0] == '') {
            // bytes from back, eg Range: bytes=-1000
            $rangeStart = $max - intval($range[1]);
        } else {
            // start at given range, eg Range: bytes=1000-2000 -> 1000
            $rangeStart = intval($range[0]);
            if ($range[1] == '') {
                $rangeEnd = $max;
            } else {
                // end at given range, eg Range: bytes=1000-2000 -> 2000
                $rangeEnd = intval($range[1]);
            }
        }

        if ($rangeStart < 0 || $rangeStart > $max || $rangeEnd > $max || $rangeEnd < $rangeStart) {
            // range out of bounds
            throw new \InvalidArgumentException('Invalid Range');
        }

        return [$rangeStart, $rangeEnd];
    }
}
