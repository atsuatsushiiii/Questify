<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            ALTER TABLE quests
                ADD COLUMN category_id BIGINT UNSIGNED DEFAULT NULL AFTER user_id,
                ADD KEY idx_quests_owner_category (user_id, category_id),
                ADD CONSTRAINT fk_quests_category FOREIGN KEY (category_id) REFERENCES categories (id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec(<<<'SQL'
            ALTER TABLE quests
                DROP FOREIGN KEY fk_quests_category,
                DROP KEY idx_quests_owner_category,
                DROP COLUMN category_id
            SQL);
    }
};
