<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Schema_Manager;

class AuthorNodeIdTest extends TestCase {

	public function test_site_person_who_is_the_author_shares_the_publisher_id(): void {
		$id = Schema_Manager::author_node_id( 1, [ 'knowledge_type' => 'Person', 'knowledge_name' => 'test author' ] );
		$this->assertSame( 'https://example.com/#person', $id );
	}

	public function test_other_authors_keep_their_own_id(): void {
		$this->assertSame( 'https://example.com/author/1#author', Schema_Manager::author_node_id( 1, [ 'knowledge_type' => 'Person', 'knowledge_name' => 'Someone Else' ] ) );
	}

	public function test_organization_sites_keep_author_ids(): void {
		$this->assertSame( 'https://example.com/author/1#author', Schema_Manager::author_node_id( 1, [ 'knowledge_type' => 'Organization', 'knowledge_name' => 'Test Author' ] ) );
	}
}
