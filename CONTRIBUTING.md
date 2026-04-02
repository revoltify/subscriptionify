# Contributing

Thank you for considering contributing to Subscriptionify!

## Bug Reports

If you discover a bug, please open an issue on [GitHub](https://github.com/revoltify/subscriptionify/issues) with a clear description, steps to reproduce, and your environment details (PHP version, Laravel version).

## Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Make your changes
4. Run the quality tools:

```bash
# Tests
./vendor/bin/pest

# Code style
./vendor/bin/pint

# Static analysis
./vendor/bin/phpstan analyse

# Code modernization
./vendor/bin/rector --dry-run
```

5. Commit your changes (`git commit -m 'Add my feature'`)
6. Push to the branch (`git push origin feature/my-feature`)
7. Open a Pull Request

## Coding Style

- Follow [Laravel coding style](https://laravel.com/docs/contributions#coding-style) (enforced via Pint)
- All files must include `declare(strict_types=1)`
- PHPStan must pass at **level max** with zero errors
- All new features must include Pest tests

## Security Vulnerabilities

If you discover a security vulnerability, please email [support@revoltify.net](mailto:support@revoltify.net) instead of opening a public issue.
