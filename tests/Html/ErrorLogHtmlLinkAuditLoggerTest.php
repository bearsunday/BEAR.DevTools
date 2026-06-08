<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

use PHPUnit\Framework\TestCase;

use function assert;
use function file_get_contents;
use function ini_set;
use function is_string;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class ErrorLogHtmlLinkAuditLoggerTest extends TestCase
{
    public function testWarningSanitizesControlCharacters(): void
    {
        $logFile = tempnam(sys_get_temp_dir(), 'bear-dev-log');
        assert(is_string($logFile));
        $previousLog = ini_set('error_log', $logFile);

        try {
            (new ErrorLogHtmlLinkAuditLogger())->warning(
                new LinkHeader("go\nNext", "/next\tpath", "po\rst"),
                "bad\nreason",
            );
            $log = file_get_contents($logFile);
            assert(is_string($log));

            $this->assertStringContainsString('rel=goNext method=post href=/nextpath reason=badreason', $log);
            $this->assertStringNotContainsString("go\nNext", $log);
            $this->assertStringNotContainsString("bad\nreason", $log);
        } finally {
            ini_set('error_log', $previousLog === false ? '' : $previousLog);
            unlink($logFile);
        }
    }
}
