# AI Contribution Guidelines / Agent Instructions

Guidelines for AI assistants contributing to DMARCo.

## Project Structure & Design
- DMARCo is a DMARC report analysis tool built on top of Symfony.
- Do not create unnecessary interfaces that are only needed for testing.
- The database is PostgreSQL.
- Only generate migrations using the command `bin/console make:migration`.

## Compatibility & Security
- Ensure compatibility with Symfony and PHP versions defined in composer.json
- Follow secure coding practices to prevent XSS, CSRF, injections, auth bypasses, etc.

## Coding Standards & Tooling
- Use 4 spaces for indentation in all files (PHP, YAML, XML, Twig, etc.)
- Use PHPUnit for unit and functional testing
- Use php-cs-fixer to ensure consistent code style
- Use Psalm for static analysis

## PHP Code
- Use modern PHP 8.4+ syntax and features
- Declare strict_types=1 in all PHP files
- Follow the Symfony Coding Standard
- Do not use deprecated features from PHP, Symfony, or any bundled libraries
- Use final for all classes, except entities and repositories
- Use readonly for immutable services and value objects
- Add type declarations for all properties, arguments, and return values
- Use camelCase for variables and method names
- Use SCREAMING_SNAKE_CASE for constants
- Use snake_case for configuration keys, route names, and template variables
- Use fast returns instead of nesting logic unnecessarily
- Use trailing commas in multi-line arrays and argument lists
- Order array keys alphabetically where applicable
- Use PHPDoc only when necessary (e.g. @var Collection<Domain>)
- Group class elements in this order: constants, properties, constructor, public methods, protected methods, private methods
- Group getter and setter methods for the same properties together
- Suffix interfaces with Interface, traits with Trait
- Use use statements for all non-global classes
- Sort use imports alphabetically and group by type (classes, functions, constants)

## API
- Use PHPUnit tests to validate API configuration and API responses
- For API response DTOs, prefer dedicated entity-to-api mappers (MicroMapper) instead of building response DTOs inline in controllers.
- For MicroMapper entity-to-DTO mappers, keep `load()` for DTO instantiation only, and populate DTO fields exclusively in `populate()`.

## PHPUnit
- Test class names must end with Test suffix
- Use #[Covers] attribute for unit tests
- Strive for 90+% test coverage

## Common Mistakes to Avoid
- Forgetting tests: API changes need PHPUnit tests in tests/Api/

## Docker
- Run project commands inside the PHP Docker container.
- Use bash in the container: `docker compose exec php bash`

Examples:
- `docker compose exec php bash -lc "vendor/bin/phpunit"`
- `docker compose exec php bash -lc "composer install"`
- `docker compose exec php bash -lc "vendor/bin/psalm"`
