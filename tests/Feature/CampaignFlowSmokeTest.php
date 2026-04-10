<?php

namespace Tests\Feature;

use Tests\TestCase;

class CampaignFlowSmokeTest extends TestCase
{
    public function test_campaign_routes_require_authentication(): void
    {
        $this->get('/campaigns')->assertRedirect('/login');
        $this->get('/products')->assertRedirect('/login');
    }
}
