<?php namespace Ajslim\FencingActions\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateActionCallTable extends Migration
{
    /**
     * Creates tables
     *
     * @return null
     */
    public function up()
    {
        Schema::connection('business')->create(
            'ajslim_fencingactions_action_call', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->integer('action_id')->unsigned();
                $table->integer('call_id')->unsigned();
                $table->foreign('action_id')
                    ->references('id')
                    ->on('ajslim_fencingactions_actions');
                $table->foreign('call_id')
                    ->references('id')
                    ->on('ajslim_fencingactions_calls');;
                $table->primary(['action_id', 'call_id']);
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
        Schema::connection('business')->dropIfExists('ajslim_fencingactions_action_call');
    }
}
