<?php

namespace Cesurapp\ApiBundle\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PhoneNumber extends Constraint
{
    public const ANY = 'any';
    public const FIXED_LINE = 'fixed_line';
    public const MOBILE = 'mobile';
    public const PAGER = 'pager';
    public const PERSONAL_NUMBER = 'personal_number';
    public const PREMIUM_RATE = 'premium_rate';
    public const SHARED_COST = 'shared_cost';
    public const TOLL_FREE = 'toll_free';
    public const UAN = 'uan';
    public const VOIP = 'voip';
    public const VOICEMAIL = 'voicemail';

    public function __construct(
        public array|string|null $types = null,
        public ?string $defaultRegion = null,
        public ?string $regionPath = null,
        public ?int $format = null,
        public ?string $message = null,
        mixed $options = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        $this->defaultRegion ??= 'ZZ'; // PhoneNumberUtil::UNKNOWN_REGION
        $this->types = (is_string($this->types) ? [$this->types] : $this->types) ?? [self::ANY];
        parent::__construct($options, $groups, $payload);
    }

    public function getMessage(): string
    {
        return $this->message ?? (count($this->types) > 1 ? 'This value is not a valid phone number.' : match ($this->types[0]) {
            self::FIXED_LINE => 'This value is not a valid fixed-line number.',
            self::MOBILE => 'This value is not a valid mobile number.',
            self::PAGER => 'This value is not a valid pager number.',
            self::PERSONAL_NUMBER => 'This value is not a valid personal number.',
            self::PREMIUM_RATE => 'This value is not a valid premium-rate number.',
            self::SHARED_COST => 'This value is not a valid shared-cost number.',
            self::TOLL_FREE => 'This value is not a valid toll-free number.',
            self::UAN => 'This value is not a valid UAN.',
            self::VOIP => 'This value is not a valid VoIP number.',
            self::VOICEMAIL => 'This value is not a valid voicemail access number.',
            default => 'This value is not a valid phone number.',
        });
    }
}
