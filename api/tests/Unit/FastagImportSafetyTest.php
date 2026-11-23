<?php

namespace Tests\Unit;

use Fleetbase\FleetOps\Http\Controllers\Internal\v1\FastagController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class FastagImportSafetyTest extends TestCase
{
    private FastagController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new FastagController();
    }

    #[Test]
    public function provider_agnostic_mapping_keeps_retroactive_transaction_query_unrestricted(): void
    {
        $query = new FastagProviderQuerySpy();

        $result = $this->invoke('scopeRetroactiveProviderAccount', $query, null);

        $this->assertSame($query, $result);
        $this->assertSame(0, $query->whereCalls);
    }

    #[Test]
    public function named_provider_mapping_adds_a_retroactive_transaction_constraint(): void
    {
        $query = new FastagProviderQuerySpy();

        $result = $this->invoke('scopeRetroactiveProviderAccount', $query, ' issuer-account-1 ');

        $this->assertSame($query, $result);
        $this->assertSame(1, $query->whereCalls);
        $this->assertSame(['provider_account_id'], $query->constraint->whereNullColumns);
        $this->assertSame([['provider_account_id', ''], ['provider_account_id', 'issuer-account-1']], $query->constraint->orWhereClauses);
    }

    #[Test]
    public function csv_header_normalization_accepts_utf8_bom_and_provider_formatting(): void
    {
        $header = $this->invoke('normalizeCsvHeader', ["\xEF\xBB\xBFProvider Transaction ID", 'Tag-Reference', ' Passage At ']);

        $this->assertSame(['provider_transaction_id', 'tag_reference', 'passage_at'], $header);
    }

    #[Test]
    public function blank_accounts_normalize_without_losing_zero_identifier(): void
    {
        foreach ([null, '', '   '] as $account) {
            $this->assertNull($this->invoke('normalizeProviderAccount', $account));
        }
        $this->assertSame('0', $this->invoke('normalizeProviderAccount', ' 0 '));
        $this->assertSame('issuer-1', $this->invoke('normalizeProviderAccount', ' issuer-1 '));
    }

    #[Test]
    public function blank_csv_account_uses_statement_account_and_preserves_zero(): void
    {
        $this->assertSame('batch-1', $this->invoke('resolveStatementProviderAccount', ' ', ' batch-1 '));
        $this->assertSame('row-1', $this->invoke('resolveStatementProviderAccount', ' row-1 ', 'batch-1'));
        $this->assertSame('0', $this->invoke('resolveStatementProviderAccount', '0', 'batch-1'));
        $this->assertSame('', $this->invoke('resolveStatementProviderAccount', null, null));
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionMethod($this->controller, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->controller, ...$arguments);
    }
}

class FastagProviderQuerySpy
{
    public int $whereCalls = 0;
    public FastagProviderConstraintSpy $constraint;

    public function where(callable $constraint): self
    {
        $this->whereCalls++;
        $this->constraint = new FastagProviderConstraintSpy();
        $constraint($this->constraint);

        return $this;
    }
}

class FastagProviderConstraintSpy
{
    /** @var array<int, string> */
    public array $whereNullColumns = [];

    /** @var array<int, array{0: string, 1: string}> */
    public array $orWhereClauses = [];

    public function whereNull(string $column): self
    {
        $this->whereNullColumns[] = $column;

        return $this;
    }

    public function orWhere(string $column, string $value): self
    {
        $this->orWhereClauses[] = [$column, $value];

        return $this;
    }
}
