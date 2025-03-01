<?php

declare(strict_types=1);

namespace Synolia\SyliusSchedulerCommandPlugin\Parser;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Webmozart\Assert\Assert;

class CommandParser implements CommandParserInterface
{
    /** @var string[] */
    private array $excludedNamespaces;

    public function __construct(private KernelInterface $kernel, array $excludedNamespaces = [])
    {
        Assert::allString($excludedNamespaces);
        $this->excludedNamespaces = $excludedNamespaces;
    }

    public function getCommands(): array
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput(
            [
                'command' => 'list',
                '--format' => 'json',
            ],
        );

        $stream = fopen('php://memory', 'w+');

        if ($stream === false) {
            throw new \Exception('PHP Memory stream not available');
        }

        $output = new StreamOutput($stream);
        $application->run($input, $output);
        rewind($output->getStream());

        return $this->extractCommandsFromJson((string) stream_get_contents($output->getStream()));
    }

    private function extractCommandsFromJson(string $string): array
    {
        if ($string === '') {
            return [];
        }

        $node = \json_decode($string, null, 512, \JSON_THROW_ON_ERROR);
        $commandsList = [];

        if (null === $node || !\is_array($node->namespaces)) {
            return [];
        }

        foreach ($node->namespaces as $namespace) {
            if (!in_array($namespace->id, $this->excludedNamespaces, true)) {
                foreach ($namespace->commands as $command) {
                    $commandsList[$namespace->id][$command] = $command;
                }
            }
        }

        return $commandsList;
    }
}
