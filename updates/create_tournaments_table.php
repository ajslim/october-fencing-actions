<?php namespace Ajslim\FencingActions\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateTournamentsTable extends Migration
{
    public function up()
    {

        Schema::connection('business')->create('ajslim_fencingactions_tournaments', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection('business')->dropIfExists('ajslim_fencingactions_tournaments');
    }
}
