# Emmer

Emmer is a simple AWS S3 endpoint written in PHP using Symfony as a framework. It aims to provide a minimalistic 
implementation of the S3 API that can be hosted on a cheap VPS, your typical LAMP shared hosting, or in a development
environment to simulate S3 locally.

## Current state

This project is in early development and by no means ready for production use.

| Feature              | Status |
|----------------------|--------|
| Authentication       | ❌ |
| Authorization        | ❌ |
| List buckets         | ❌ |
| Create bucket        | ❌ |
| Delete bucket        | ❌ |
| List objects         | ✅ |
| Get/Head object      | ✅ |
| Put object (v1 + v2) | ✅ |
| Multi-part uploads   | ✅❌ |
| Delete object        | ✅ |
| Delete objects       | ✅ |
| Versioning           | ❌ |
| Regions              | ❌ |
| Signed URLs          | ❌ |
| Slim controllers     | ❌ |
| Coding standards     | ❌ |
| Unit tests           | ❌ |
| Static analyser      | ❌ |

A checkbox means it has some support, not that it's fully implemented and 100% compatible with every feature of the S3 API.

## Requirements

* PHP 8.2+ with SimpleXML

