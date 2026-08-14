<?php

namespace App\Services\Batches;

class BatchInputContext
{
    /**
     * @param array<int, array<string, mixed>> $storedFiles
     */
    public function __construct(
        public readonly int $authUserId,
        public readonly string $batchType,
        public readonly ?int $requestTypeId,
        public readonly ?int $minRange,
        public readonly ?int $maxRange,
        public readonly array $storedFiles,
        public readonly string $userWelcomeEmailMode = 'none',
        public readonly ?string $userWelcomeEmailRecipient = null,
    ) {
    }

    /**
     * El contexto viaja dentro del payload del job (JSON), por eso se serializa
     * a array plano en vez de depender de la serialización de objetos de la cola.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'authUserId' => $this->authUserId,
            'batchType' => $this->batchType,
            'requestTypeId' => $this->requestTypeId,
            'minRange' => $this->minRange,
            'maxRange' => $this->maxRange,
            'storedFiles' => $this->storedFiles,
            'userWelcomeEmailMode' => $this->userWelcomeEmailMode,
            'userWelcomeEmailRecipient' => $this->userWelcomeEmailRecipient,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            authUserId: (int) ($payload['authUserId'] ?? 0),
            batchType: (string) ($payload['batchType'] ?? ''),
            requestTypeId: isset($payload['requestTypeId']) ? (int) $payload['requestTypeId'] : null,
            minRange: isset($payload['minRange']) ? (int) $payload['minRange'] : null,
            maxRange: isset($payload['maxRange']) ? (int) $payload['maxRange'] : null,
            storedFiles: is_array($payload['storedFiles'] ?? null) ? $payload['storedFiles'] : [],
            userWelcomeEmailMode: (string) ($payload['userWelcomeEmailMode'] ?? 'none'),
            userWelcomeEmailRecipient: $payload['userWelcomeEmailRecipient'] ?? null,
        );
    }
}
