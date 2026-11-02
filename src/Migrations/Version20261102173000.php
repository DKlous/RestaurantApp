<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional explicit dietary labels to menu items.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_item ADD dietary_labels JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_item DROP dietary_labels');
    }
}