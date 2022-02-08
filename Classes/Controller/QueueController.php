<?php
declare(strict_types=1);

namespace Wwwision\Neos\JobQueueModule\Controller;

use Flowpack\JobQueue\Common\Exception as JobQueueException;
use Flowpack\JobQueue\Common\Job\JobInterface;
use Flowpack\JobQueue\Common\Queue\Message;
use Flowpack\JobQueue\Common\Queue\QueueInterface;
use Flowpack\JobQueue\Common\Queue\QueueManager;
use Neos\Error\Messages\Message as ErrorMessage;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Security\Authorization\PrivilegeManagerInterface;
use Neos\Flow\Security\Exception\AccessDeniedException;

final class QueueController extends ActionController
{

    private QueueManager $queueManager;
    private PrivilegeManagerInterface $privilegeManager;

    /**
     * @Flow\InjectConfiguration(package="Flowpack.JobQueue.Common", path="queues")
     * @var array
     */
    protected array $jobQueueConfigurations;

    public function __construct(QueueManager $queueManager, PrivilegeManagerInterface $privilegeManager)
    {
        $this->queueManager = $queueManager;
        $this->privilegeManager = $privilegeManager;
    }

    /**
     * @throws AccessDeniedException|JobQueueException
     */
    public function indexAction(): void
    {
        $allowedQueues = $this->determineAllowedQueues();
        if ($allowedQueues === []) {
            throw new AccessDeniedException('You don\'t have access to any of the configured JobQueues. Please add a corresponding Policy.yaml configuration', 1643807999);
        }
        if (\count($allowedQueues) === 1) {
            $this->forward('show', null, null, ['queueName' => $allowedQueues[array_key_first($allowedQueues)]['name']]);
        }
        $this->view->assign('queues', $allowedQueues);
    }

    /**
     * @throws AccessDeniedException
     */
    public function showAction(string $queueName): void
    {
        if (!$this->isQueueAccessGranted($queueName)) {
            throw new AccessDeniedException(sprintf('You don\'t have access to the specified queue "%s". Please add a corresponding Policy.yaml configuration', $queueName), 1643809841);
        }
        $queue = $this->getQueue($queueName);

        $this->view->assignMultiple([
            'queue' => $this->queueDescriptor($queue),
            'messages' => array_map(fn(Message $message) => $this->messageDescriptor($message), $queue->peek(10)),
        ]);
    }

    /**
     * @throws AccessDeniedException
     */
    public function finishMessageAction(string $queueName, string $messageId): void
    {
        if (!$this->privilegeManager->isPrivilegeTargetGranted('Wwwision.Neos.JobQueueModule:Queues.Any.FinishMessages') && !$this->privilegeManager->isPrivilegeTargetGranted('Wwwision.Neos.JobQueueModule:Queues.Specific.FinishMessages', ['queue' => $queueName])) {
            throw new AccessDeniedException(sprintf('You are not allowed to finish messages from the specified queue "%s". Please add a corresponding Policy.yaml configuration', $queueName), 1643809841);
        }
        $queue = $this->getQueue($queueName);
        $queue->finish($messageId);
        $this->addFlashMessage('Message "%s" was removed from queue "%s"', 'Message finished', ErrorMessage::SEVERITY_NOTICE, [$messageId, $queue->getName()]);
        $this->redirect('show', null, null, ['queueName' => $queue->getName()]);
    }

    // ------------------------------

    private function determineAllowedQueues(): array
    {
        return array_map(
            fn(string $queueName) => $this->queueDescriptorByName($queueName),
            array_filter(
                array_keys($this->jobQueueConfigurations),
                fn(string $queueName) => $this->isQueueAccessGranted($queueName)
            )
        );
    }

    private function isQueueAccessGranted(string $queueName): bool
    {
        return $this->privilegeManager->isPrivilegeTargetGranted('Wwwision.Neos.JobQueueModule:Queues.Any.Access') || $this->privilegeManager->isPrivilegeTargetGranted('Wwwision.Neos.JobQueueModule:Queues.Specific.Access', ['queue' => $queueName]);
    }

    private function getQueue(string $queueName): QueueInterface
    {
        try {
            return $this->queueManager->getQueue($queueName);
        } catch (JobQueueException $e) {
            throw new \RuntimeException(sprintf('Failed to load queue with name "%s", please check your configuration.', $queueName), 1643815137, $e);
        }
    }

    private function queueDescriptorByName(string $queueName): array
    {
        return $this->queueDescriptor($this->getQueue($queueName));
    }

    private function queueDescriptor(QueueInterface $queue): array
    {
        return [
            'name' => $queue->getName(),
            'reserved' => $queue->countReserved(),
            'ready' => $queue->countReady(),
            'failed' => $queue->countFailed(),
        ];
    }

    private function messageDescriptor(Message $message): array
    {
        $job = unserialize($message->getPayload(), ['allowed_classes' => true]);
        $reflection = new \ReflectionClass($job);

        if ($job instanceof \__PHP_Incomplete_Class) {
            $jobLabel = 'Missing class!';
        } elseif ($job instanceof JobInterface) {
            $jobLabel = $job->getLabel();
        } else {
            $jobLabel = sprintf('Invalid class "%s"', $reflection->getName());
        }
        return [
            'identifier' => $message->getIdentifier(),
            'numberOfReleases' => $message->getNumberOfReleases(),
            'payload' => $message->getPayload(),
            'jobLabel' => $jobLabel,
            'jobClassName' => $reflection->getShortName(),
        ];
    }
}
