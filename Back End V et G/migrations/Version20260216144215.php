<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260216144215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        //$this->addSql('CREATE TABLE `order` (id INT AUTO_INCREMENT NOT NULL, service_address VARCHAR(255) NOT NULL, service_city VARCHAR(130) NOT NULL, service_date DATE NOT NULL, service_time TIME NOT NULL, km NUMERIC(10, 2) DEFAULT NULL, people_count INT NOT NULL, menu_price NUMERIC(10, 2) NOT NULL, delivery_price NUMERIC(10, 2) NOT NULL, discount NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, status VARCHAR(30) NOT NULL, cancel_reason LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, menu_id INT NOT NULL, INDEX IDX_F5299398A76ED395 (user_id), INDEX IDX_F5299398CCD7E912 (menu_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        //$this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        //$this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id)');
        //$this->addSql('ADD UNIQUE INDEX UNIQ_6B7BA4B65F37A13B  password_reset_token (token)');
        $this->addSql('ALTER TABLE user ADD allergies LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398A76ED395');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398CCD7E912');
        $this->addSql('DROP TABLE `order`');
        $this->addSql('DROP INDEX UNIQ_6B7BA4B65F37A13B ON password_reset_token');
        $this->addSql('ALTER TABLE user DROP allergies');
    }
}
