<?php

namespace Cesurapp\ApiBundle\Tests\Validator;

use Cesurapp\ApiBundle\Tests\_App\Entity\User;
use Cesurapp\ApiBundle\Validator\EntityExists;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class EntityExistsValidatorTest extends KernelTestCase
{
    protected function setUp(): void
    {
        static::bootKernel();
        $this->initDatabase(self::$kernel);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testUnique(): void
    {
        $validator = self::getContainer()->get('validator');
        $em = self::getContainer()->get('doctrine')->getManager();

        $user = new User();
        $user->setEmail('acme@acme.test');
        $em->persist($user);
        $em->flush();

        $class = new ExistsDummy();

        $class->user = $user->getId();
        $this->assertSame(0, $validator->validateProperty($class, 'user')->count());
        $class->user = 2;
        $this->assertSame(1, $validator->validateProperty($class, 'user')->count());
    }

    private function initDatabase(KernelInterface $kernel): void
    {
        if ('test' !== $kernel->getEnvironment()) {
            throw new \LogicException('Execution only in Test environment possible!');
        }

        $entityManager = $kernel->getContainer()->get('doctrine')->getManager();
        $metaData = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->updateSchema($metaData);
    }
}

class ExistsDummy
{
    #[EntityExists(entityClass: User::class, colName: 'id')]
    public User|int $user;
}
