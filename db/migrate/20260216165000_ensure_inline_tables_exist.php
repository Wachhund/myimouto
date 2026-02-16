<?php
class EnsureInlineTablesExist extends Rails\ActiveRecord\Migration\Base
{
    public function up()
    {
        if (!$this->tableExists('inlines')) {
            $this->createTable('inlines', function($t) {
                $t->integer('user_id', ['null' => false]);
                $t->text('description');
                $t->timestamps();
            });
        }

        if (!$this->tableExists('inline_images')) {
            $this->createTable('inline_images', function($t) {
                $t->integer('inline_id', ['null' => false]);
                $t->string('md5', 32);
                $t->string('file_ext', 4);
                $t->text('description');
                $t->integer('sequence');
                $t->integer('width');
                $t->integer('height');
                $t->integer('sample_width');
                $t->integer('sample_height');
                $t->timestamps();
            });
            $this->addIndex('inline_images', 'inline_id');
        }
    }
}
