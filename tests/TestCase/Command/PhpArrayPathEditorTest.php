<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\TestCase\Command;

use Cake\Console\Exception\ConsoleException;
use CakeGraphQL\Command\PhpArrayPathEditor;
use PHPUnit\Framework\TestCase;

final class PhpArrayPathEditorTest extends TestCase
{
    private const PATH = ['Graphql', 'engines', 'Graphqlite', 'queries'];

    public function testAddsStringValueToNestedArrayPath(): void
    {
        $updated = $this->editor()->addStringValue(
            <<<'PHP'
<?php
return [
    'Graphql' => [
        'engines' => [
            'Graphqlite' => [
                'queries' => [
                ],
            ],
        ],
    ],
];
PHP,
            self::PATH,
            'App\\Graphql\\UsersQuery',
            'missing',
        );

        $this->assertStringContainsString("'App\\\\Graphql\\\\UsersQuery',", $updated);
    }

    public function testPreservesDynamicExpressionsOutsideEditedArray(): void
    {
        $updated = $this->editor()->addStringValue(
            <<<'PHP'
<?php
return [
    'Security' => [
        'salt' => env('SECURITY_SALT'),
    ],
    'Graphql' => [
        'engines' => [
            'Graphqlite' => [
                'queries' => [
                ],
            ],
        ],
    ],
];
PHP,
            self::PATH,
            'App\\Graphql\\UsersQuery',
            'missing',
        );

        $this->assertStringContainsString("'salt' => env('SECURITY_SALT')", $updated);
        $this->assertStringContainsString("'App\\\\Graphql\\\\UsersQuery',", $updated);
    }

    public function testDoesNotDuplicateClassConstantEntry(): void
    {
        $contents = <<<'PHP'
<?php
return [
    'Graphql' => [
        'engines' => [
            'Graphqlite' => [
                'queries' => [
                    App\Graphql\UsersQuery::class,
                ],
            ],
        ],
    ],
];
PHP;

        $updated = $this->editor()->addStringValue(
            $contents,
            self::PATH,
            'App\\Graphql\\UsersQuery',
            'missing',
        );

        $this->assertSame($contents, $updated);
    }

    public function testSupportsInlineEmptyArray(): void
    {
        $updated = $this->editor()->addStringValue(
            <<<'PHP'
<?php
return [
    'Graphql' => [
        'engines' => [
            'Graphqlite' => [
                'queries' => [],
            ],
        ],
    ],
];
PHP,
            self::PATH,
            'App\\Graphql\\UsersQuery',
            'missing',
        );

        $this->assertStringContainsString(<<<'PHP'
                'queries' => [
                    'App\\Graphql\\UsersQuery',
                ],
PHP, $updated);
    }

    public function testThrowsConfiguredMessageWhenPathIsMissing(): void
    {
        $this->expectException(ConsoleException::class);
        $this->expectExceptionMessage('configured missing path message');

        $this->editor()->addStringValue(
            <<<'PHP'
<?php
return [
    'Graphql' => [],
];
PHP,
            self::PATH,
            'App\\Graphql\\UsersQuery',
            'configured missing path message',
        );
    }

    private function editor(): PhpArrayPathEditor
    {
        return new PhpArrayPathEditor();
    }
}
