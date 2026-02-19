<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218000703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE order_status_history (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(30) NOT NULL, changed_at DATETIME NOT NULL, order_ref_id INT NOT NULL, INDEX IDX_471AD77EE238517C (order_ref_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE order_status_history ADD CONSTRAINT FK_471AD77EE238517C FOREIGN KEY (order_ref_id) REFERENCES orders (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_status_history DROP FOREIGN KEY FK_471AD77EE238517C');
        $this->addSql('DROP TABLE order_status_history');
    }
}
