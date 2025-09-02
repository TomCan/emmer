# Emmer

Emmer is a simple AWS S3 endpoint written in PHP using Symfony as a framework. It aims to provide a minimalistic 
implementation of the S3 API that can be hosted on a cheap VPS, your typical LAMP shared hosting, or in a development
environment to simulate S3 locally.

## Documentation
For installation and usage, please refer to the [docs](docs/README.md).

## Current state

This project is in early development and by no means ready for production use.

| Feature                      | Status |
|------------------------------|--------|
| Authentication               | ✅      |
| - V4 Headers                 | ✅      |
| - V4 Signed URLs             | ✅      |
| Authorization                | ✅      |
| - Policy system              | ✅      |
| Buckets                      | ✅      |
| - List buckets               | ✅      |
| - Head bucket                | ✅      |
| - Create bucket              | ✅      |
| - Delete bucket              | ✅      |
| - Create bucket policy       | ✅      |
| - Get bucket policy          | ✅      |
| - Delete bucket policy       | ✅      |
| Objects                      | ✅      |
| - List objects (v1 + v2)     | ✅      |
| - Get/Head object            | ✅      |
| - Put object                 | ✅      |
| - Delete object              | ✅      |
| - Delete objects             | ✅      |
| Multi-part uploads           | ✅      |
| - Create MPU                 | ✅      |
| - Abort MPU                  | ✅      |
| - Complete MPU               | ✅      |
| - List MPU                   | ✅      |
| - UploadPart                 | ✅      |
| - UploadPartCopy             | ❌      |
| - ListParts                  | ✅      |
| Versioning                   | ✅      |
| - List object versions       | ✅      |
| - Get/Delete object versions | ✅      |
| - Get buckets versioning     | ✅      |
| - Put buckets versioning     | ✅      |
| - Delete markers             | ✅      |
| Maintencance                 | ❌      |
| - Lifecycle policies         | ❌      |
| - MPU cleanup                | ❌      |
| Regions (low prio)           | ❌      |
| Storage classes (low prio)   | ❌      |

| Project features | Status |
|------------------|--------|
| Documentation    | ✅❌     |
| Slim controllers | ✅      |
| Services         | ✅      |
| Exceptions       | ✅      |
| Coding standards | ✅      |
| Unit tests       | ✅❌     |
| Static analyser  | ✅      |

A checkbox means it has some support, not that it's fully implemented and 100% compatible with every feature of the S3 API.

## Requirements

* PHP 8.2+ with SimpleXML

