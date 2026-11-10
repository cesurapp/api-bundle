<?php

namespace Cesurapp\ApiBundle\Validator;

use libphonenumber\PhoneNumberFormat;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
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

    /** Formats for `format`: `+905321112233`, `+90 532 111 22 33`, `0532 111 22 33`, `tel:+90-532-111-22-33`. */
    public const FORMAT_E164 = 'e164';
    public const FORMAT_INTERNATIONAL = 'international';
    public const FORMAT_NATIONAL = 'national';
    public const FORMAT_RFC3966 = 'rfc3966';

    /** @var list<string> */
    public array $types;

    /**
     * @param string|list<string>|null          $types
     * @param int|string|PhoneNumberFormat|null $format a valid number leaves the validated property in this format: a
     *                                                  FORMAT_* name, or libphonenumber's PhoneNumberFormat constant
     *                                                  (an int in 8, an enum case in 9)
     */
    #[HasNamedArguments]
    public function __construct(
        array|string|null $types = null,
        public ?string $defaultRegion = null,
        public ?string $regionPath = null,
        public int|string|PhoneNumberFormat|null $format = null,
        public ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        $this->defaultRegion ??= 'ZZ'; // PhoneNumberUtil::UNKNOWN_REGION
        $this->types = null === $types ? [self::ANY] : (array) $types;
        parent::__construct(null, $groups, $payload);
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
