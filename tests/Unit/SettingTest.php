<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

use Eos\Common\SettingsSchema;
use Eos\Common\SettingPack;
use Eos\Common\Setting;
use Eos\Common\Exceptions\SettingException;

class SettingTest extends ApiTestCase
{
    /**
     * An exhaustive class unit test.
     *
     * @return void
     */
    public function test_we_can_validate_schema()
    {
      print( 'test_we_can_validate_schema' . PHP_EOL );
      $this->expectNotToPerformAssertions();
       SettingsSchema::validate();
    }
    
    public function test_we_can_fail_schema_scalar()
    {
      print( 'test_we_can_fail_schema_scalar' . PHP_EOL );
      $this->expectException(SettingException::class);

      SettingsSchema::validate( ['scalar_element' => ['type' => 'number', 'bogus' => ''] ]);
      $this->fail("Expected exception not thrown");

    }
    
    public function test_we_can_fail_schema_groups()
    {
      print( 'test_we_can_fail_schema_groups' . PHP_EOL );
      $this->expectException(Exception::class);
      $this->withoutExceptionHandling();

        SettingsSchema::validate( [
             'schema' => ['type' => 'group',
                 'fields' => [
                     'services' => ['type' => 'multigroup', 'extensible' => true,
                             'fields' => [
                                 'name' => ['type' => 'text', 'sample' => 'Micro Service'],
                                 'class' => ['type' => 'text', 'sample' => 'App\MyClass'],
                                 'connections' => ['type' => 'group',
                                       'fields' => [
                                           'outbound' => ['type' => 'group',
                                               'fields' => [
                                                   "url" => ["type"=>"text","sample"=>"http://path.to.service"],
                                                   "authentication" => ["type"=>"bogus","valid"=>["oauth","apikey","none"]],
                                                   "clientid"=>["type"=>"text","sample"=>"20"],
                                               ],
                                           ],
                                       ], 
                                 ], 
                             ], 
                     ],
                 ]
             ] ] );
        $this->fail("Expected exception not thrown");

    }
    
    public function test_we_can_get_schema()
    {
      print( 'test_we_can_get_schema' . PHP_EOL );
      $schema = SettingsSchema::get();
      $this->assertJson(json_encode($schema),json_encode(['eos','gumdrop']));
    }
    
    public function test_we_can_get_default_values()
    {
      print( 'test_we_can_get_default_values' . PHP_EOL );
      $settings = SettingsSchema::getSchemaDefaults();
      $this->assertEquals($settings['gumdrop']['color'],'red');
    }
    
    public function test_we_can_get_effective_packs()
    {
      print( 'test_we_can_get_effective_packs' . PHP_EOL );
      $packs = SettingPack::inEffectiveOrder()->get();
      $this->assertEquals( $packs->count(), 1 ); // the 1 default pack added by the seeder
    }
    
    public function test_we_can_set_current_pack()
    {
      print( 'test_we_can_set_current_pack' . PHP_EOL );
      $start = new Carbon();
      $end = (new Carbon())->addHour();  //give us time to use it before it expires
      SettingPack::create(['quantum_start' => $start->timestamp,
                           'quantum_end' => $end->timestamp,
                           'pack' => ["gumdrop" => [ "color" => "blue"]] ]);
      $packs = SettingPack::inEffectiveOrder()->get();
      $this->assertEquals( $packs->count(), 2 );

    }
    
    public function test_we_can_get_current_color()
    {
      print( 'test_we_can_get_current_color' . PHP_EOL );
      Setting::clearCache();
      $color = Setting::get('gumdrop.color');
      $this->assertEquals('blue', $color);

      $this->assertTrue( cache()->has( Setting::getCacheKey() ) );
      $cached = json_decode( cache()->get( Setting::getCacheKey() ) );

      $this->assertEquals('blue', $cached->gumdrop->color);
    }

    public function test_we_can_get_gumdrop_settings()
    {
        print( 'test_we_can_get_gumdrop_settings' . PHP_EOL );
        $color = Setting::get('gumdrop');
        $this->assertArrayHasKey('color', $color);
    }

    public function test_we_can_change_gumdrop_setting()
    {
        print( 'test_we_can_change_gumdrop_setting' . PHP_EOL );
        Setting::set('gumdrop.color', 'green');
        $color = Setting::get('gumdrop.color');
        $this->assertEquals('green', $color);
    }
}
    
