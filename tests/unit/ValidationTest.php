<?php

use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase {

	public function test_valid_donation_type_is_accepted() {
		$this->assertTrue( Matrix_Donations_Validation::is_valid_donation_type( 'single' ) );
		$this->assertTrue( Matrix_Donations_Validation::is_valid_donation_type( 'monthly' ) );
		$this->assertFalse( Matrix_Donations_Validation::is_valid_donation_type( 'membership' ) );
	}

	public function test_amount_parsing_accepts_presets() {
		$this->assertSame( 1000, Matrix_Donations_Validation::parse_amount_to_cents( '10', null ) );
		$this->assertSame( 25000, Matrix_Donations_Validation::parse_amount_to_cents( '250', null ) );
	}

	public function test_amount_parsing_accepts_custom_range() {
		$this->assertSame( 1234, Matrix_Donations_Validation::parse_amount_to_cents( 'custom', '12.34' ) );
		$this->assertNull( Matrix_Donations_Validation::parse_amount_to_cents( 'custom', '0' ) );
		$this->assertNull( Matrix_Donations_Validation::parse_amount_to_cents( 'custom', '100001' ) );
	}

	public function test_amount_parsing_rejects_non_preset_fixed_values() {
		$this->assertNull( Matrix_Donations_Validation::parse_amount_to_cents( '11', null ) );
	}
}
