<?php
declare(strict_types=1);

namespace Taimoor\AiContent\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Reads the module settings from Stores > Configuration > Taimoor > AI Content Generator.
 */
class Config
{
    private const PATH = 'taimoor_aicontent/general/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'enabled');
    }

    public function getBaseUrl(): string
    {
        return rtrim((string) $this->scopeConfig->getValue(self::PATH . 'base_url'), '/');
    }

    public function getApiKey(): string
    {
        $value = (string) $this->scopeConfig->getValue(self::PATH . 'api_key');

        return $value === '' ? '' : $this->encryptor->decrypt($value);
    }

    public function getModel(): string
    {
        return trim((string) $this->scopeConfig->getValue(self::PATH . 'model'));
    }

    public function getLanguage(): string
    {
        return trim((string) $this->scopeConfig->getValue(self::PATH . 'language')) ?: 'English';
    }

    public function getTone(): string
    {
        return trim((string) $this->scopeConfig->getValue(self::PATH . 'tone')) ?: 'Professional, clear and friendly';
    }

    public function shouldOverwrite(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'overwrite');
    }
}
