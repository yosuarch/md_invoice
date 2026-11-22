<?php

declare(strict_types=1);

use App\Libraries\BackupSql;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * SQL text of backups: every value escaped so a statement never spans lines; statements read back line by line.
 *
 * @internal
 */
final class BackupSqlTest extends CIUnitTestCase
{
    public function testValues(): void
    {
        $this->assertSame('NULL', BackupSql::value(null));
        $this->assertSame("'12.50'", BackupSql::value('12.50'));
        $this->assertSame("'it\\'s \\\"x\\\"'", BackupSql::value('it\'s "x"'));
        $this->assertSame("'a\\\\b'", BackupSql::value('a\\b'));
        $this->assertSame("'line1\\nline2\\r\\0\\Z'", BackupSql::value("line1\nline2\r\0\x1a"));
        $this->assertSame('0x00ff41', BackupSql::value("\x00\xffA", true));
        $this->assertSame("''", BackupSql::value('', true));
    }

    public function testInsertIsOneLine(): void
    {
        $sql = BackupSql::insert('note', ['id', 'text', 'blob'], [[1, "multi\nline;", "\x01"], ['id' => 2, 'text' => null, 'blob' => null]], ['blob' => true]);

        $this->assertSame("INSERT INTO `note` (`id`,`text`,`blob`) VALUES ('1','multi\\nline;',0x01),('2',NULL,NULL);", $sql);
        $this->assertStringNotContainsString("\n", $sql);
    }

    public function testOneLineAndStatements(): void
    {
        $create = BackupSql::oneLine("CREATE TABLE `t` (\n  `id` int(10) NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB");
        $this->assertSame('CREATE TABLE `t` ( `id` int(10) NOT NULL, PRIMARY KEY (`id`) ) ENGINE=InnoDB;', $create);

        $statements = iterator_to_array(BackupSql::statements(["-- comment\n", "\n", "SET NAMES utf8mb4;\n", $create . "\r\n", "INSERT INTO `t` VALUES ('a;b');"]), false);
        $this->assertSame(['SET NAMES utf8mb4', substr($create, 0, -1), "INSERT INTO `t` VALUES ('a;b')"], $statements);
    }

    public function testIdentifierAndBinaryTypes(): void
    {
        $this->assertSame('`a``b`', BackupSql::identifier('a`b'));
        $this->assertTrue(BackupSql::isBinaryType('varbinary(16)'));
        $this->assertTrue(BackupSql::isBinaryType('longblob'));
        $this->assertFalse(BackupSql::isBinaryType('varchar(255)'));
        $this->assertFalse(BackupSql::isBinaryType('text'));
    }
}
