<?php

namespace Bernard;

/**
 * @package Bernard
 */
class Consumer
{
    protected $services;
    protected $shutdown = false;
    protected $defaults = array(
        'max-retries' => 5,
        'max-runtime' => PHP_INT_MAX,
    );

    /**
     * @param ServiceResolver $services
     */
    public function __construct(ServiceResolver $services)
    {
        if (!extension_loaded('pcntl')) {
            throw new \RuntimeException('The pcntl extension is missing.');
        }

        if (!extension_loaded('posix')) {
            throw new \RuntimeException('The posix extension is missing.');
        }

        $this->services = $services;
    }

    /**
     * {@inheritDoc}
     */
    public function consume(Queue $queue, Queue $failed = null, array $options = array())
    {
        declare(ticks = 1);

        $options = array_merge($this->defaults, array_filter($options));
        $runtime = microtime(true) + $options['max-runtime'];

        pcntl_signal(SIGTERM, array($this, 'trap'), true);
        pcntl_signal(SIGINT, array($this, 'trap'), true);

        echo sprintf('Main PID: %s', posix_getpid()) . PHP_EOL;

        while (microtime(true) < $runtime && !$this->shutdown) {

            echo '-----------------------' . PHP_EOL;

            if (!$envelope = $queue->dequeue()) {
                continue;
            }

            try {
                if (0 === $forked = $this->fork()) {

                    echo sprintf('Child PID: %s', posix_getpid()) . PHP_EOL;

                    throw new \RuntimeException();

                    $message = $envelope->getMessage();
                    $invocator = $this->services->resolve($message);
                    $invocator->invoke();
                    exit(0);
                }

                if (0 < $forked) {

                    echo sprintf('Parent PID: %s', posix_getpid()) . PHP_EOL;

                    $forkedPid = pcntl_wait($status);

                    if (!pcntl_wifexited($status)) {
                        throw new \RuntimeException(sprintf(
                            'Forked pid %s did not teminiate normally.', $forkedPid
                        ));
                    }

                    if (0 !== $exitCode = pcntl_wexitstatus($status)) {
                        throw new \RuntimeException(sprintf(
                            'Forked pid %s exited with exit code %s.', $forkedPid, $exitCode
                        ));
                    }
                }
            } catch (\Exception $e) {
                if ($envelope->getRetries() < $options['max-retries']) {
                    $envelope->incrementRetries();
                    $queue->enqueue($envelope);

                    posix_kill(posix_getpid(), SIGTERM);

                    continue;
                }

                if ($failed) {
                    $failed->enqueue($envelope);
                }
            }
        }

        echo sprintf('Final PID: %s', posix_getpid()) . PHP_EOL;
    }

    /**
     * Mark consumer as terminating
     */
    public function trap()
    {
        $this->shutdown = true;
    }


    /**
     * Fork the currently running consumer
     *
     * @throws \RuntimeException
     */
    protected function fork()
    {
        $pid = pcntl_fork();

        if (-1 === $pid) {
            throw new \RuntimeException(sprintf('Cannot fork %s.', posix_getpid()));
        }

        return $pid;
    }
}
