<?php

declare(strict_types=1);

namespace Drupal\Tests\bm_xlsx\Kernel;

use Drupal\bm_xlsx\BMXLSX\BMXLSX;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\File\FileSystemInterface;

/**
 * Kernel tests for BMXLSX upload and parsing helpers.
 */
class BMXLSXKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'file',
    'user',
    'bm_main',
    'bm_xlsx',
  ];

  /**
   * Path to a generated XLSX file for each test.
   */
  private string $xlsxUri;

  /**
   * Set up a temporary XLSX fixture.
   */
  protected function setUp(): void {
    parent::setUp();

    $file_system = $this->container->get('file_system');
    assert($file_system instanceof FileSystemInterface);

    $file_system->prepareDirectory('public://', FileSystemInterface::CREATE_DIRECTORY);
    $this->xlsxUri = 'public://bm_xlsx_kernel_test.xlsx';

    $data = [
      ['Name', 'Age'],
      ['Alice', '30'],
      ['Bob', '25'],
    ];

    BMXLSX::save_as_excel($data, $this->xlsxUri);
    $this->assertFileExists($file_system->realpath($this->xlsxUri));
  }

  /**
   * Ensure sheet metadata and row parsing work for a freshly saved XLSX.
   *
   * @covers \Drupal\bm_xlsx\BMXLSX\BMXLSX::getSheetNames
   * @covers \Drupal\bm_xlsx\BMXLSX\BMXLSX::getRowHeader
   * @covers \Drupal\bm_xlsx\BMXLSX\BMXLSX::getRowsKeyed
   */
  public function testSheetInterpretation(): void {
    $xlsx = new BMXLSX($this->xlsxUri);

    $this->assertSame(['Sheet1'], $xlsx->getSheetNames());
    $this->assertSame([
      'Name' => 'Name',
      'Age' => 'Age',
    ], $xlsx->getRowHeader());

    $rows = $xlsx->getRowsKeyed();
    $this->assertCount(2, $rows);
    $this->assertSame(['Name' => 'Alice', 'Age' => '30'], $rows[0]);
    $this->assertSame(['Name' => 'Bob', 'Age' => '25'], $rows[1]);
  }

  /**
   * Ensure caching helpers persist the sheet and limit selection.
   *
   * @covers \Drupal\bm_xlsx\BMXLSX\BMXLSX::cache_config_sheet_selection_and_limit
   * @covers \Drupal\bm_xlsx\BMXLSX\BMXLSX::get_config_sheet_selection_and_limit
   */
  public function testSelectionCaching(): void {
    BMXLSX::cache_config_sheet_selection_and_limit('0', 50);
    $config = BMXLSX::get_config_sheet_selection_and_limit();

    $this->assertSame('0', $config->sheet);
    $this->assertSame(50, $config->limit);
  }

}
