<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional menu allergen information and an alcohol indicator.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_item ADD allergens JSON DEFAULT NULL, ADD is_alcoholic TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_item DROP allergens, DROP is_alcoholic');
    }
}