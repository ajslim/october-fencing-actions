<?php namespace Ajslim\FencingActions\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateActionTagTable extends Migration
{
    /**
     * Creates tables
     *
     * @return null
     */
    public function up()
    {
        Schema::connection('business')->create(
            'ajslim_fencingactions_action_tag', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->integer('action_id')->unsigned();
                $table->integer('tag_id')->unsigned();
                $table->primary(['action_id', 'tag_id']);
            }
        );
    }

    /**
     * Removes tables
     *
     * @return null
     */
    public function down()
    {
        Schema::connection('business')->dropIfExists('ajslim_fencingactions_action_tag');
    }
}
