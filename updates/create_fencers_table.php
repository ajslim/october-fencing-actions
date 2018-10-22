<?php namespace Ajslim\FencingActions\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateFencersTable extends Migration
{
    public function up()
    {

        Schema::connection('business')->create('ajslim_fencingactions_fencers', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('gender', 1);
            $table->string('fie_number')
                ->nullable();
            $table->string('fie_site_number')
                ->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('country_code', 3)
                ->nullable();
            $table->date('birth')
                ->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection('business')->dropIfExists('ajslim_fencingactions_fencers');
    }
}
