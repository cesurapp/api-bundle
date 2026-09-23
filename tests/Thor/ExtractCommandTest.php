<?php

namespace Cesurapp\ApiBundle\Tests\Thor;

use Cesurapp\ApiBundle\Thor\Command\ThorGenerateCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class ExtractCommandTest extends KernelTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/thor_command_test_'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->dir);
        parent::tearDown();
        restore_exception_handler();
    }

    public function testCommand(): void
    {
        $tester = $this->tester();
        $tester->execute(['path' => './var/api']);
        $tester->assertCommandIsSuccessful();
        $this->assertFileExists('./var/api/index.ts');
        $this->assertFileExists('./var/api/.thor');
    }

    public function testReplacesAPreviouslyGeneratedDirectory(): void
    {
        $this->tester()->execute(['path' => $this->dir]);
        file_put_contents($this->dir.'/stale.ts', 'old');

        $tester = $this->tester();
        $tester->execute(['path' => $this->dir]);

        $tester->assertCommandIsSuccessful();
        $this->assertFileDoesNotExist($this->dir.'/stale.ts');
    }

    /**
     * An older client has no marker but has the index.ts + flatten.ts pair.
     */
    public function testReplacesALegacyClientDirectory(): void
    {
        mkdir($this->dir);
        file_put_contents($this->dir.'/index.ts', '');
        file_put_contents($this->dir.'/flatten.ts', '');

        $tester = $this->tester();
        $tester->execute(['path' => $this->dir]);

        $tester->assertCommandIsSuccessful();
        $this->assertFileExists($this->dir.'/.thor');
    }

    public function testRefusesToDeleteAnUnrelatedDirectory(): void
    {
        mkdir($this->dir);
        file_put_contents($this->dir.'/important.txt', 'keep me');

        $tester = $this->tester();
        $this->assertSame(Command::FAILURE, $tester->execute(['path' => $this->dir]));
        $this->assertFileExists($this->dir.'/important.txt');

        $tester->execute(['path' => $this->dir, '--force' => true]);
        $tester->assertCommandIsSuccessful();
        $this->assertFileDoesNotExist($this->dir.'/important.txt');
    }

    /**
     * Checked on temporary directories only: a wrong answer here must not be able to delete anything real.
     */
    public function testProjectDirectoryAndItsParentsAreProtected(): void
    {
        $project = $this->dir.'/workspace/app';
        mkdir($project.'/var/api', 0777, true);
        mkdir($this->dir.'/workspace/frontend');

        $this->assertTrue(ThorGenerateCommand::containsProject($project, $project));
        $this->assertTrue(ThorGenerateCommand::containsProject($project.'/', $project));
        $this->assertTrue(ThorGenerateCommand::containsProject($this->dir.'/workspace', $project));
        $this->assertTrue(ThorGenerateCommand::containsProject($project.'/var/..', $project));
        $this->assertFalse(ThorGenerateCommand::containsProject($project.'/var/api', $project));
        $this->assertFalse(ThorGenerateCommand::containsProject($this->dir.'/workspace/frontend', $project));
        $this->assertFalse(ThorGenerateCommand::containsProject($this->dir.'/workspace/ap', $project));
        $this->assertFalse(ThorGenerateCommand::containsProject($this->dir.'/missing', $project));
    }

    private function tester(): CommandTester
    {
        self::bootKernel();

        return new CommandTester(new Application(self::$kernel)->find('thor:extract'));
    }
}
