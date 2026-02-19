<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260212150719 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE allergen (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE dish_allergen (dish_id INT NOT NULL, allergen_id INT NOT NULL, INDEX IDX_3C4389A5148EB0CB (dish_id), INDEX IDX_3C4389A56E775A4A (allergen_id), PRIMARY KEY (dish_id, allergen_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE dish_allergen ADD CONSTRAINT FK_3C4389A5148EB0CB FOREIGN KEY (dish_id) REFERENCES dish (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dish_allergen ADD CONSTRAINT FK_3C4389A56E775A4A FOREIGN KEY (allergen_id) REFERENCES allergen (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu CHANGE min_price min_price NUMERIC(10, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dish_allergen DROP FOREIGN KEY FK_3C4389A5148EB0CB');
        $this->addSql('ALTER TABLE dish_allergen DROP FOREIGN KEY FK_3C4389A56E775A4A');
        $this->addSql('DROP TABLE allergen');
        $this->addSql('DROP TABLE dish_allergen');
        $this->addSql('ALTER TABLE menu CHANGE min_price min_price NUMERIC(10, 0) DEFAULT NULL');
    }
}
