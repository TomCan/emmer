# Emmer

Emmer is a simple AWS S3 endpoint written in PHP using Symfony as a framework. It aims to provide a light-weight,
minimalistic implementation of the S3 API that can be hosted on simple PHP webhosting. This could be a cheap VPS, your
typical LAMP shared hosting, or in a local development environment using docker or even just the PHP built-in webserver.

You can use it in situations where a full-blown AWS S3 is not required, not feasible, not desired or maybe even not 
allowed due to internal compliance reasons. By self-hosting, you take control of all aspect of your data.

## S3 Compatibility

Emmmer aims to be compatible with AWS S3, making it suitable as a replacement for use with many S3 capable applications.
It doesn't implement each and every AWS S3 feature, but focusses on a solid baseline to support most use-cases.

| Authentication & Authorization | Bucket management            | Object management      |
|--------------------------------|------------------------------|------------------------|
| Signature V4                   | List, Create, Delete buckets | List, Get, Put, Delete |
| Policy based authorization     | Manage bucket policies       | Multi-part uploads     |
| Signed URLs                    | Manage CORS configuration    | Version management     |
|                                | Lifecycle configuration      | Lifecycle processing   |
|                                | Versioned buckets            |                        |

### What is doesn't do

Emmer is not a full AWS S3 replacement. There are some limitations you need to keep in mind. 
Emmer does not offer support for encryption at rest, storage types and transitions, bucket replication, regions, 
event/lambda system, or even a management console. Also, not every AWS S3 feature or header is implemented in the
supported API calls.

Although we want to support as much use-cases as possible, there's currently no guarantee, let alone timeline or roadmap
for implementing these features.

## Requirements

* PHP 8.2+ with SimpleXML
* MySQL / SQLite3 (not recommended)
* Hosting must support REST API methods (GET/POST/PUT/DELETE/OPTIONS)

## Documentation
For installation and usage, please refer to the [docs](docs/README.md).

## License

The Emmer source code is released under the MIT license:

Copyright © 2025 - present Tom Cannaerts

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation
files (the “Software”), to deal in the Software without restriction, including without limitation the rights to use, copy, 
modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following conditions:
 
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software. 

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE 
WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR 
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR 
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
