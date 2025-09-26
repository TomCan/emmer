<?php

namespace App\Tests\Service\CorsService;

use App\DataFixtures\BaseTestFixture;
use App\DataFixtures\CorsTestFixture;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CorsWebTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        // Boot the Symfony kernel and get the container
        $this->client = static::createClient();
        $container = static::getContainer();
        // Get services
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        // Rollback transaction to clean up database changes
        $this->entityManager->close();

        parent::tearDown();
    }

    private function loadFixtures(): void
    {
        $loader = new Loader();
        $loader->addFixture(new BaseTestFixture());
        $loader->addFixture(new CorsTestFixture());
        $executor = new ORMExecutor($this->entityManager, new ORMPurger($this->entityManager));
        $executor->execute($loader->getFixtures());
    }


    public function testWebCorsHeaders(): void
    {
        $this->loadFixtures();

        $headers = [
            'HTTP_HOST' => 'emmer.emr',
            'HTTP_ORIGIN' => 'emmer.emr',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ];

        // POST + emmer.emr origin + bucket path = allowed
        $crawler = $this->client->request('OPTIONS', '/regular-bucket', [], [], $headers);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Access-Control-Allow-Origin', 'emmer.emr');
        $this->assertResponseHeaderSame('Access-Control-Allow-Methods', 'GET, PUT, POST, DELETE');

        // POST + emmer.emr origin + subpath = allowed
        $crawler = $this->client->request('OPTIONS', '/regular-bucket/my-file', [], [], $headers);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Access-Control-Allow-Origin', 'emmer.emr');
        $this->assertResponseHeaderSame('Access-Control-Allow-Methods', 'GET, PUT, POST, DELETE');

        // PUT + put.emmer.emr origin = allowed
        $headers['HTTP_ORIGIN'] = 'put.emmer.emr';
        $headers['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'PUT';
        $crawler = $this->client->request('OPTIONS', '/regular-bucket', [], [], $headers);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Access-Control-Allow-Origin', 'put.emmer.emr');
        $this->assertResponseHeaderSame('Access-Control-Allow-Methods', 'PUT');

        // POST + put.emmer.emr origin = not allowed
        $headers['HTTP_ORIGIN'] = 'put.emmer.emr';
        $headers['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'POST';
        $crawler = $this->client->request('OPTIONS', '/regular-bucket', [], [], $headers);
        $this->assertResponseStatusCodeSame(403);

        // GET + any.emmer.emr origin = allowed
        $headers['HTTP_ORIGIN'] = 'any.emmer.emr';
        $headers['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'GET';
        $crawler = $this->client->request('OPTIONS', '/regular-bucket', [], [], $headers);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Access-Control-Allow-Origin', '*');
        $this->assertResponseHeaderSame('Access-Control-Allow-Methods', 'GET');

        // GET + header.emmer.emr origin + content-type header = allowed
        $headers['HTTP_ORIGIN'] = 'header.emmer.emr';
        $headers['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'GET';
        $headers['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] = 'content-type';
        $crawler = $this->client->request('OPTIONS', '/regular-bucket', [], [], $headers);
        $this->assertResponseIsSuccessful();

        // GET + header.emmer.emr origin + x-emmer header = allowed
        $headers['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] = 'x-emmer';
        $crawler = $this->client->request('OPTIONS', '/regular-bucket', [], [], $headers);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Access-Control-Allow-Headers', 'content-type, x-emmer');

        // GET + header.emmer.emr origin + x-emmer header + content-type header = allowed
        $headers['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] = 'x-emmer,content-type';
        $crawler = $this->client->request('OPTIONS', '/regular-bucket', [], [], $headers);
        $this->assertResponseIsSuccessful();

        // GET + header.emmer.emr origin + x-tom = not allowed
        $headers['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] = 'x-tom';
        $crawler = $this->client->request('OPTIONS', '/regular-bucket', [], [], $headers);
        $this->assertResponseStatusCodeSame(403);

        // GET + header.emmer.emr origin + x-emmer header + x-tom = not allowed
        $headers['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] = 'x-emmer,x-tom';
        $crawler = $this->client->request('OPTIONS', '/regular-bucket', [], [], $headers);
        $this->assertResponseStatusCodeSame(403);

        // GET + maxage.emmer.emr = must have maxage header
        $headers['HTTP_ORIGIN'] = 'maxage.emmer.emr';
        $headers['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'PUT';
        unset($headers['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
        $crawler = $this->client->request('OPTIONS', '/regular-bucket', [], [], $headers);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Access-Control-Max-Age', '3600');

        // GET + expose.emmer.emr = must have expose header
        $headers['HTTP_ORIGIN'] = 'expose.emmer.emr';
        $headers['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'PUT';
        $crawler = $this->client->request('OPTIONS', '/regular-bucket', [], [], $headers);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Access-Control-Expose-Headers', 'x-emmer');
    }
}
