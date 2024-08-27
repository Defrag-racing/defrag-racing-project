# Contribution Guidelines

Thank you for your interest in contributing to [defrag.racing](https://defrag.racing) project! We welcome all contributions from bug reports to pull requests and documentation improvements. Here is a list of ways you can contribute:

## Create an Issue

If you discover a bug or have an idea for a new feature or an enhancement to an existing one, please create an issue in our [issue tracker](https://github.com/neyoneit/defrag-racing-project/issues).

For bugs, include as much detail as you can. Provide steps to reproduce the bug, expected result and actual result. Screenshots and received error messages can also be helpful.

For new ideas or enhancements, provide a detailed description of your suggestion.

Before creating an issue, please check if someone else has already reported the bug or suggested the same idea. This helps to avoid duplicate issues.

## Development

If you're interested in fixing a bug or developing a new feature, please check our [issue tracker](https://github.com/neyoneit/defrag-racing-project/issues) and choose an issue that suits you.

Once you've started working on an issue, please create a draft pull request and assign the issue you're working on to it. This lets the community know that someone is handling it.

## Writing tests

Testing is a crucial part of ensuring the stability and reliability of our code. Whether you're contributing new code or not, we encourage you to write tests. This could be for existing functionality, to prevent regressions, or for new features you're adding.

To run the existing tests, use the following command:

```bash
./vendor/bin/sail artisan test
```

Remember, good tests can help catch bugs and prevent regressions, making the project more robust and maintainable.

## Documentation

Documentation is just as important as code! If you see a place where the documentation could be improved, please make those changes and submit a pull request.

## Pull Requests

If you're ready to contribute code, here's how you can do it:

1. Fork the repository and clone it locally.
2. Create a branch for your edits.
3. Write your code.
4. Test your changes to ensure they work as expected.
5. Commit your changes. Include a good and descriptive commit message.
6. Push your changes to your fork.
7. Submit a pull request.

General guidelines:

* To avoid duplicate work, create a draft pull request and assigne issue to it.
* Avoid cosmetic changes to unrelated files in the same commit.
* Use a rebase workflow for all PRs. After addressing review comments, it's fine to force-push.
* When writing code, try to replicate coding style that surrounds your changes.

Nice to have:
* Include test coverage.
* Follow the [conventional commits guidelines](https://www.conventionalcommits.org/en/v1.0.0/).

## Questions

If you have any questions about contributing, setting up the development environment, or even how to play DeFRaG, please don't hesitate to reach out to us on our [Discord](https://discord.defrag.racing/) or create an issue in [issue tracker](https://github.com/neyoneit/defrag-racing-project/issues). We're here to help and will do our best to assist you.

**Thank you again for your interest in contributing to our project. We look forward to working with you!**
