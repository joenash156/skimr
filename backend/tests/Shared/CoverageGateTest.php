<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Coverage\CoverageGate;
use PHPUnit\Framework\TestCase;

final class CoverageGateTest extends TestCase
{
    private const CLOVER = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <coverage generated="1"><project timestamp="1">
          <file name="C:\repo\backend\src\Auth\LoginService.php"><metrics statements="100" coveredstatements="80"/></file>
          <file name="/repo/backend/src/Auth/Jwt/JwtService.php"><metrics statements="50" coveredstatements="45"/></file>
          <file name="/repo/backend/src/Security/RolePolicy.php"><metrics statements="40" coveredstatements="20"/></file>
          <file name="/repo/backend/src/Shared/Db.php"><metrics statements="10" coveredstatements="0"/></file>
          <metrics files="4" statements="200" coveredstatements="145"/>
        </project></coverage>
        XML;

    public function testSumsPerFolderOnWindowsAndUnixPaths(): void
    {
        $result = CoverageGate::evaluate(self::CLOVER, ['src/Auth' => 70.0, 'src/Security' => 70.0]);

        self::assertSame(['covered' => 125, 'total' => 150, 'percent' => 83.3, 'minimum' => 70.0, 'ok' => true], $result['src/Auth']);
        self::assertSame(50.0, $result['src/Security']['percent']);
        self::assertFalse($result['src/Security']['ok']);
    }

    public function testAFolderWithNoMeasuredLinesFails(): void
    {
        self::assertFalse(CoverageGate::evaluate(self::CLOVER, ['src/Ai' => 70.0])['src/Ai']['ok']);
    }

    public function testRejectsAnInvalidReport(): void
    {
        $this->expectException(\RuntimeException::class);
        CoverageGate::evaluate('not xml', ['src/Auth' => 70.0]);
    }
}
