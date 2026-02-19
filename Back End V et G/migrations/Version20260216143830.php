<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260216143830 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

   public function up(Schema $schema): void
{
    // Table orders already exists (created/renamed manually)
    // No SQL needed here to avoid failing migration.
}


    public function down(Schema $schema): void
{
    // No rollback for this manual alignment
}

}
