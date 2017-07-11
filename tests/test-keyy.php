<?php

class test_keyy extends WP_UnitTestCase
{
    /**
     * @covers Keyy_Login_Plugin::instance
     */
    public function test_instance()
    {
        $this->assertTrue( Keyy_Login_Plugin::instance() instanceof Keyy_Login_Plugin );
    }

    /**
     * @covers Keyy_Login_Plugin::login_instance
     */
    public function test_login_instance()
    {
        $this->assertTrue( Keyy_Login_Plugin::instance()->login_instance() instanceof Keyy_Login );
    }

    /**
     * @covers Keyy_Login_Plugin::get_notices
     */
    public function test_get_notices()
    {
        $this->assertTrue( Keyy_Login_Plugin::instance()->get_notices() instanceof Keyy_Notices );
    }

    /**
     * @covers Keyy_Login_Plugin::capability_required
     */
    public function test_capability_required()
    {
        $obj = Keyy_Login_Plugin::instance();

        $this->assertSame( 'read',          $obj->capability_required( ) );
        $this->assertSame( 'read',          $obj->capability_required( 'user' ) );
        $this->assertSame( 'create_users',  $obj->capability_required( 'kjsadhfkjdh' ) );

        add_filter(
                    'keyy_capability_required',
                    function( $capability_required, $for )
                    {
                        return 'cheese';
                    },
                    10,
                    2
                );

        $this->assertSame( 'cheese', $obj->capability_required( ) );
        $this->assertSame( 'cheese', $obj->capability_required( 'user' ) );
        $this->assertSame( 'cheese', $obj->capability_required( 'kjsadhfkjdh' ) );
    }

    /**
     * @covers Keyy_Login_Plugin::show_admin_warning
     */
    public function test_show_admin_warning()
    {
        ob_start();
        Keyy_Login_Plugin::instance()->show_admin_warning( 'Gerp', 'Terp' );
        $buffer = ob_get_clean();

        $this->assertSame( '<div class="notice is-dismissible keyy_message Terp"><p>Gerp</p></div>', $buffer );

        ob_start();
        Keyy_Login_Plugin::instance()->show_admin_warning( 'Gerp' );
        $buffer = ob_get_clean();

        $this->assertSame( '<div class="notice is-dismissible keyy_message updated"><p>Gerp</p></div>', $buffer );
    }

    /**
     * @covers Keyy_Login_Plugin::get_common_urls
     */
    public function test_get_common_urls()
    {
        $expected = array(
                            'home_page',
                            'home_url',
                            'keyy_premium_shop',
                            'wp_plugin',
                            'support_forum',
                            'support',
                            'faqs',
                            'faq_how_to_disable',
                            'upcoming_features',
                            'review_url',
                            'android_app',
                            'ios_app',
                            'updraftplus_landing',
                            'updraftcentral_landing',
                            'wp_optimize_landing',
                            'simba_plugins_landing',
                            'sso_information'
        );

        $urls = Keyy_Login_Plugin::instance()->get_common_urls();

        foreach( $expected as $url )
        {
            $this->assertArrayHasKey( $url, $urls );
            unset( $urls[ $url ] );
        }

        $this->assertCount( 0, $urls );
    }
}
