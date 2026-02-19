<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260217143734 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE review (id INT AUTO_INCREMENT NOT NULL, rating SMALLINT NOT NULL, comment LONGTEXT NOT NULL, is_validated TINYINT NOT NULL, created_at DATETIME NOT NULL, validated_at DATETIME DEFAULT NULL, order_ref_id INT NOT NULL, user_ref_id INT NOT NULL, UNIQUE INDEX UNIQ_794381C6E238517C (order_ref_id), INDEX IDX_794381C644E55A94 (user_ref_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6E238517C FOREIGN KEY (order_ref_id) REFERENCES orders (id)');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C644E55A94 FOREIGN KEY (user_ref_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY `FK_F5299398A76ED395`');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY `FK_F5299398CCD7E912`');
        $this->addSql('DROP TABLE `order`');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEECCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id)');
        $this->addSql('DROP INDEX idx_f5299398a76ed395 ON orders');
        $this->addSql('CREATE INDEX IDX_E52FFDEEA76ED395 ON orders (user_id)');
        $this->addSql('DROP INDEX idx_f5299398ccd7e912 ON orders');
        $this->addSql('CREATE INDEX IDX_E52FFDEECCD7E912 ON orders (menu_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `order` (id INT AUTO_INCREMENT NOT NULL, service_address VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, service_city VARCHAR(130) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, service_date DATE NOT NULL, service_time TIME NOT NULL, km NUMERIC(10, 2) DEFAULT NULL, people_count INT NOT NULL, menu_price NUMERIC(10, 2) NOT NULL, delivery_price NUMERIC(10, 2) NOT NULL, discount NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, status VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, cancel_reason LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, user_id INT NOT NULL, menu_id INT NOT NULL, INDEX IDX_F5299398A76ED395 (user_id), INDEX IDX_F5299398CCD7E912 (menu_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT `FK_F5299398A76ED395` FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT `FK_F5299398CCD7E912` FOREIGN KEY (menu_id) REFERENCES menu (id)');
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C6E238517C');
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C644E55A94');
        $this->addSql('DROP TABLE review');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEEA76ED395');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEECCD7E912');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEEA76ED395');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEECCD7E912');
        $this->addSql('DROP INDEX idx_e52ffdeea76ed395 ON orders');
        $this->addSql('CREATE INDEX IDX_F5299398A76ED395 ON orders (user_id)');
        $this->addSql('DROP INDEX idx_e52ffdeeccd7e912 ON orders');
        $this->addSql('CREATE INDEX IDX_F5299398CCD7E912 ON orders (menu_id)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEECCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id)');
    }
}
