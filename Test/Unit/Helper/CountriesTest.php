<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Helper;

use Adyen\ExpressCheckout\Helper\Countries;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Directory\Api\Data\CountryInformationInterface;
use Magento\Framework\Reflection\DataObjectProcessor;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CountriesTest extends AbstractAdyenTestCase
{
    private CountryInformationAcquirerInterface $countryInformationAcquirerMock;
    private DataObjectProcessor $dataProcessorMock;
    private Countries $countries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->countryInformationAcquirerMock = $this->createMock(CountryInformationAcquirerInterface::class);
        $this->dataProcessorMock = $this->createMock(DataObjectProcessor::class);

        $this->countries = new Countries(
            $this->countryInformationAcquirerMock,
            $this->dataProcessorMock
        );
    }

    public function testGetCountriesReturnsBuiltOutputForCountriesWithLocaleName(): void
    {
        $countryNl = $this->createMock(CountryInformationInterface::class);
        $countryNl->method('getFullNameLocale')->willReturn('Netherlands');

        $countryUs = $this->createMock(CountryInformationInterface::class);
        $countryUs->method('getFullNameLocale')->willReturn('United States');

        $this->countryInformationAcquirerMock->expects($this->once())
            ->method('getCountriesInfo')
            ->willReturn([$countryNl, $countryUs]);

        $nlOutput = ['id' => 'NL', 'full_name_locale' => 'Netherlands'];
        $usOutput = ['id' => 'US', 'full_name_locale' => 'United States'];

        $this->dataProcessorMock->expects($this->exactly(2))
            ->method('buildOutputDataArray')
            ->willReturnMap([
                [$countryNl, CountryInformationInterface::class, $nlOutput],
                [$countryUs, CountryInformationInterface::class, $usOutput],
            ]);

        $result = $this->countries->getCountries();

        $this->assertSame([$nlOutput, $usOutput], $result);
    }

    public function testGetCountriesSkipsCountriesWithEmptyLocaleName(): void
    {
        $countryWithName = $this->createMock(CountryInformationInterface::class);
        $countryWithName->method('getFullNameLocale')->willReturn('Germany');

        $countryWithoutName = $this->createMock(CountryInformationInterface::class);
        $countryWithoutName->method('getFullNameLocale')->willReturn('');

        $this->countryInformationAcquirerMock->expects($this->once())
            ->method('getCountriesInfo')
            ->willReturn([$countryWithName, $countryWithoutName]);

        $deOutput = ['id' => 'DE', 'full_name_locale' => 'Germany'];

        $this->dataProcessorMock->expects($this->once())
            ->method('buildOutputDataArray')
            ->with($countryWithName, CountryInformationInterface::class)
            ->willReturn($deOutput);

        $result = $this->countries->getCountries();

        $this->assertSame([$deOutput], $result);
    }

    public function testGetCountriesReturnsEmptyArrayWhenNoCountries(): void
    {
        $this->countryInformationAcquirerMock->expects($this->once())
            ->method('getCountriesInfo')
            ->willReturn([]);

        $this->dataProcessorMock->expects($this->never())
            ->method('buildOutputDataArray');

        $this->assertSame([], $this->countries->getCountries());
    }
}
