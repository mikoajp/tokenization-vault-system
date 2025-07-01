<?php

namespace App\Domain\Audit\Models;

use App\Domain\Audit\ValueObjects\AuditLogId;
use App\Domain\Audit\ValueObjects\OperationType;
use App\Domain\Audit\ValueObjects\RiskLevel;
use App\Domain\Audit\Events\AuditLogCreated;
use App\Domain\Audit\Events\SecurityPatternDetected;

class AuditLog
{
    private AuditLogId $id;
    private ?string $vaultId;
    private ?string $tokenId;
    private OperationType $operation;
    private string $result;
    private ?string $errorMessage;
    private ?string $userId;
    private ?string $apiKeyId;
    private ?string $sessionId;
    private ?string $ipAddress;
    private ?string $userAgent;
    private ?string $requestId;
    private array $requestMetadata;
    private array $responseMetadata;
    private int $processingTimeMs;
    private RiskLevel $riskLevel;
    private bool $pciRelevant;
    private string $complianceReference;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        AuditLogId $id,
        OperationType $operation,
        string $result,
        RiskLevel $riskLevel,
        bool $pciRelevant,
        string $complianceReference,
        ?string $vaultId = null,
        ?string $tokenId = null,
        ?string $errorMessage = null,
        ?string $userId = null,
        ?string $apiKeyId = null,
        ?string $sessionId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $requestId = null,
        array $requestMetadata = [],
        array $responseMetadata = [],
        int $processingTimeMs = 0
    ) {
        $this->id = $id;
        $this->vaultId = $vaultId;
        $this->tokenId = $tokenId;
        $this->operation = $operation;
        $this->result = $result;
        $this->errorMessage = $errorMessage;
        $this->userId = $userId;
        $this->apiKeyId = $apiKeyId;
        $this->sessionId = $sessionId;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->requestId = $requestId;
        $this->requestMetadata = $requestMetadata;
        $this->responseMetadata = $responseMetadata;
        $this->processingTimeMs = $processingTimeMs;
        $this->riskLevel = $riskLevel;
        $this->pciRelevant = $pciRelevant;
        $this->complianceReference = $complianceReference;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): AuditLogId
    {
        return $this->id;
    }

    public function getVaultId(): ?string
    {
        return $this->vaultId;
    }

    public function getTokenId(): ?string
    {
        return $this->tokenId;
    }

    public function getOperation(): OperationType
    {
        return $this->operation;
    }

    public function getResult(): string
    {
        return $this->result;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getApiKeyId(): ?string
    {
        return $this->apiKeyId;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function getRequestMetadata(): array
    {
        return $this->requestMetadata;
    }

    public function getResponseMetadata(): array
    {
        return $this->responseMetadata;
    }

    public function getProcessingTimeMs(): int
    {
        return $this->processingTimeMs;
    }

    public function getRiskLevel(): RiskLevel
    {
        return $this->riskLevel;
    }

    public function isPciRelevant(): bool
    {
        return $this->pciRelevant;
    }

    public function getComplianceReference(): string
    {
        return $this->complianceReference;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isSuccessful(): bool
    {
        return $this->result === 'success';
    }

    public function isFailed(): bool
    {
        return $this->result === 'error';
    }

    public function isHighRisk(): bool
    {
        return $this->riskLevel->isHigh();
    }

    public function shouldTriggerAlert(): bool
    {
        return $this->isFailed() || 
               $this->isHighRisk() ||
               $this->operation->isUnauthorizedAccess();
    }

    public function getProcessingTimeInSeconds(): float
    {
        return $this->processingTimeMs / 1000;
    }

    public function toComplianceArray(): array
    {
        return [
            'audit_id' => $this->id->getValue(),
            'timestamp' => $this->createdAt->format(\DateTimeInterface::ISO8601),
            'vault_id' => $this->vaultId,
            'operation' => $this->operation->getValue(),
            'result' => $this->result,
            'user_context' => [
                'user_id' => $this->userId,
                'api_key_id' => $this->apiKeyId,
                'ip_address' => $this->ipAddress,
                'session_id' => $this->sessionId,
            ],
            'compliance_reference' => $this->complianceReference,
            'pci_relevant' => $this->pciRelevant,
            'risk_level' => $this->riskLevel->getValue(),
            'processing_time_ms' => $this->processingTimeMs,
        ];
    }
}