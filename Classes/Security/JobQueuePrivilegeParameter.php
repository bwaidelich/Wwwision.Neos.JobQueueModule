<?php
declare(strict_types=1);
namespace Wwwision\Neos\JobQueueModule\Security;

use Cloud\ProjectManagement\ValueObject\MemberRole;
use Neos\Flow\Security\Authorization\Privilege\Parameter\PrivilegeParameterInterface;

final class JobQueuePrivilegeParameter implements PrivilegeParameterInterface
{

    private string $name;
    private string $queueName;

    public function __construct(string $name, string $value)
    {
        $this->name = $name;
        $this->queueName = $value;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue()
    {
        return $this->queueName;
    }

    public function getPossibleValues(): ?array
    {
        return null;
    }

    public function validate($value): bool
    {
        return true;
    }

    public function getType(): string
    {
        return 'JobQueue Name';
    }

    public function __toString(): string
    {
        return $this->queueName;
    }
}
