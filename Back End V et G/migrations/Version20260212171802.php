<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260212171802 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE menu_image CHANGE menu_id menu_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` CHANGE service_data service_date DATE NOT NULL');
        $this->addSql('ALTER TABLE picture CHANGE restaurant_id restaurant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE update_at updated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE menu_image CHANGE menu_id menu_id INT NOT NULL');
        $this->addSql('ALTER TABLE `order` CHANGE service_date service_data DATE NOT NULL');
        $this->addSql('ALTER TABLE picture CHANGE restaurant_id restaurant_id INT NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE updated_at update_at DATETIME DEFAULT NULL');
    }
}
