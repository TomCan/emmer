# Getting started

## Installing the application

* Run `composer install --no-dev` to install dependencies.
* Create `.env.local` and set your database URL (see .env for an example).
* Execute `php bin/console doctrine:migrations:migrate` to create the database schema.
* Make sure `var/storage` is writable by your webserver/php-fpm user.

## Create a user and access key

* Run `php bin/console app:user:create <username>` to create a regulare user.
* Run `php bin/console app:user:create <username> -r` to create a root user.

Note that access keys belonging to root users have full access to all buckets and APIs.
User and/or bucket policies do not apply to root users.

* Run `php bin/console app:user:create-access-key <username>` to create an access key.

You are now ready to use the API either through aws-cli, or a custom client. We also provide some `bin/console` commands
to help you get started.

## Create a bucket (command)

* Run `php bin/console app:bucket:create <bucketname> <owner>` to create a bucket.

The owner will be granted full access on the bucket through the bucket policy. Note that overwriting the bucket policy
can lock out the owner of the bucket.

## Create and attach a policy (command)

Creating and attaching a policy is done through the command `app:policy:create`. You can either pass the policy as a
parameter using the `-p` option or point to a file using the `-f` option.

Use either `-u <username>` option to link the policy to a user, or `-b <bucketname>` to link the policy to a bucket.
 
Examples:
* `php bin/console app:policy:create SomePolicy -p '{Statement: [{...}]}' -b my-bucket`
* `php bin/console app:policy:create SomePolicy -f somefile.json -u tom`

To reference a bucket in a policy, use 'emr:bucket:<bucketname>' as the resource.  
To reference a user in a policy, use 'emr:user:<username>' as the resource. There's a special user 'emr:user:@anonymous' that
can be used to reference the root user.  
To reference a role in a policy, use 'emr:role:<rolename>' as the resource. Roles are taken from the users table, but there's
currently no way to create or assign roles other than directly in the database.

## Using the AWS CLI

You can add the access key to your .aws/credentials file and use the aws-cli to create buckets, assign policies, etc.

```
# .aws/credentials
[my-emmer]
aws_access_key_id: <EMR... access key>
aws_secret_access_key: <secret>
endpoint_url = https://your-emmer-install
```

Then you can run your regulare commands by specifying the profile
```
# list buckets
$ aws --profile=my-emmer s3 ls
# list bucket contents
$ aws --profile=my-emmer s3 ls s3://my-bucket
# upload a file
$ aws --profile=my-emmer s3 cp somefile.txt s3://my-bucket/somefile.txt
```
