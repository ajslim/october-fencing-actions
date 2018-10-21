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
            $table->string('year', 4); // This is different on the FIE site than the start date
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('fie_id');
            $table->string('country_code', 3);
            $table->string('place');
            $table->string('weapon', 1);
            $table->string('gender', 1);
            $table->string('category', 1);
            $table->string('type', 3);
            $table->string('event', 1);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection('business')->dropIfExists('ajslim_fencingactions_tournaments');
    }
}
