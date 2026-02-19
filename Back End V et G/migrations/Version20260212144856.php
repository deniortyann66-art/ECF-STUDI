<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260212144856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` ADD service_address VARCHAR(255) NOT NULL, ADD service_city VARCHAR(130) NOT NULL, ADD service_data DATE NOT NULL, ADD service_time TIME NOT NULL, ADD km NUMERIC(10, 2) DEFAULT NULL, ADD people_count INT NOT NULL, ADD menu_price NUMERIC(10, 2) NOT NULL, ADD delivery_price NUMERIC(10, 2) NOT NULL, ADD discount NUMERIC(10, 2) NOT NULL, ADD total NUMERIC(10, 2) NOT NULL, ADD status VARCHAR(30) NOT NULL, ADD cancel_reason LONGTEXT DEFAULT NULL, ADD created_at DATETIME NOT NULL, ADD menu_id INT NOT NULL');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id)');
        $this->addSql('CREATE INDEX IDX_F5299398CCD7E912 ON `order` (menu_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398CCD7E912');
        $this->addSql('DROP INDEX IDX_F5299398CCD7E912 ON `order`');
        $this->addSql('ALTER TABLE `order` DROP service_address, DROP service_city, DROP service_data, DROP service_time, DROP km, DROP people_count, DROP menu_price, DROP delivery_price, DROP discount, DROP total, DROP status, DROP cancel_reason, DROP created_at, DROP menu_id');
    }
}
