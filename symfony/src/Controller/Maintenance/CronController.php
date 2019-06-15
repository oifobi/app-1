<?php

namespace App\Controller\Maintenance;

use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @Route(path="cron", name="cron_")
 */
class CronController extends AbstractController
{
    const CRONS = [
        'user:cron',
        'import:volunteer',
        'import:skill',
    ];

    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @Route("/{key}")
     */
    public function run(Request $request, string $key,  KernelInterface $kernel)
    {
        $key = str_replace('-', ':', $key);
        if (!in_array($key, self::CRONS)) {
            throw $this->createNotFoundException();
        }

        if ($request->getClientIp() !== '127.0.0.1'
            && 'true' !== $request->headers->get('X-Appengine-Cron')) {
            throw $this->createAccessDeniedException();
        }

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput(array_merge([
            'command' => $key,
        ], $request->query->all()));

        $application->run($input, new NullOutput());

        return new Response();
    }
}