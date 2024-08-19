<?php

declare(strict_types=1);

namespace Examples;

use AdrienBrault\Instructrice\Attribute\Prompt;

class Contact
{
    public string $firstName;
    public string $lastName;
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $company = null;
    #[Prompt('The role/position at the company.')]
    public ?string $role = null;
}
