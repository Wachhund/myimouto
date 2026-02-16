<?php
class EnsureAdvertisementColumns extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        if (!$this->tableExists('advertisements')) {
            return;
        }

        if (!$this->column_exists('advertisements', 'html')) {
            $this->execute('ALTER TABLE `advertisements` ADD COLUMN `html` text NULL');
        }

        if (!$this->column_exists('advertisements', 'position')) {
            $this->execute('ALTER TABLE `advertisements` ADD COLUMN `position` char(1) NULL');
        }

        if ($this->column_exists('advertisements', 'image_url')) {
            $this->execute('ALTER TABLE `advertisements` MODIFY `image_url` varchar(255) NULL');
        }
        if ($this->column_exists('advertisements', 'referral_url')) {
            $this->execute('ALTER TABLE `advertisements` MODIFY `referral_url` varchar(255) NULL');
        }
        if ($this->column_exists('advertisements', 'width')) {
            $this->execute('ALTER TABLE `advertisements` MODIFY `width` int NULL');
        }
        if ($this->column_exists('advertisements', 'height')) {
            $this->execute('ALTER TABLE `advertisements` MODIFY `height` int NULL');
        }
    }

    protected function column_exists($table, $column)
    {
        return (bool)$this->connection->selectValue(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            $table,
            $column
        );
    }
}
