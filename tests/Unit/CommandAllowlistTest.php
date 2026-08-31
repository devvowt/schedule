<?php

namespace Tests\Unit;

use App\Services\Execution\CommandTaskExecutor;
use Tests\TestCase;

class CommandAllowlistTest extends TestCase
{
    public function test_extrai_o_binario_do_comando(): void
    {
        $this->assertSame('php', CommandTaskExecutor::binaryOf('php artisan queue:work --tries=1'));
        $this->assertSame('curl', CommandTaskExecutor::binaryOf('  curl -X POST https://x.test '));
        $this->assertSame('php', CommandTaskExecutor::binaryOf('/usr/local/bin/php -v'));
    }

    public function test_permite_binarios_da_lista(): void
    {
        config()->set('scheduler.commands.allowlist_enabled', true);
        config()->set('scheduler.commands.allowlist', ['php', 'echo']);

        $this->assertTrue(CommandTaskExecutor::isAllowed('php artisan inspire'));
        $this->assertTrue(CommandTaskExecutor::isAllowed('echo ola'));
        $this->assertFalse(CommandTaskExecutor::isAllowed('rm -rf /'));
        $this->assertFalse(CommandTaskExecutor::isAllowed('bash -c "curl evil"'));
    }

    public function test_allowlist_desativada_permite_tudo(): void
    {
        config()->set('scheduler.commands.allowlist_enabled', false);

        $this->assertTrue(CommandTaskExecutor::isAllowed('qualquer-coisa --com-flags'));
    }
}
