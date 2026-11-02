<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102160400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reservation duration and allow cancelled/overlapping historical slots to be reused.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation ADD duration_minutes INT DEFAULT 90 NOT NULL');
        $this->addSql('ALTER TABLE reservation DROP INDEX uniq_reservation_slot');
        $this->addSql('CREATE INDEX idx_reservation_table_date ON reservation (table_id, date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_reservation_table_date ON reservation');
        $this->addSql('ALTER TABLE reservation ADD UNIQUE INDEX uniq_reservation_slot (table_id, date, time)');
        $this->addSql('ALTER TABLE reservation DROP duration_minutes');
    }
}
