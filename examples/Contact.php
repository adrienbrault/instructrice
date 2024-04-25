<?php

declare(strict_types=1);

namespace Examples;

use AdrienBrault\Instructrice\Attribute\Prompt;

class Contact
{
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $company = null;
    #[Prompt('The role/position at the company.')]
    public ?string $role = null;
}
