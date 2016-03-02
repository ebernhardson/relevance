<?php

namespace WikiMedia\RelevanceScoring\Import;

class ImportedResultTest extends \PHPUnit_Framework_TestCase {
	public function testSomething() {
		$result = ImportedResult::createFromURL(
			'unittest',
			'https://en.wikipedia.org/wiki/John_F._Kennedy',
			'yabba <em>dabba</em> do',
			1
		);

		$this->assertEquals('unittest', $result->getSource());
		$this->assertEquals('John F. Kennedy', $result->getTitle());
		$this->assertEquals('yabba <em>dabba</em> do', $result->getSnippet());
		$this->assertEquals(1, $result->getPosition());
	}
}
