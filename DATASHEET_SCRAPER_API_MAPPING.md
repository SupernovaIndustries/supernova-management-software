# Datasheet Scraper - API Field Mappings Reference

## Mouser API Field Mappings

### Mouser API Response Structure

```json
{
  "MouserPartNumber": "187-CL10A226MP8NUNE",
  "ManufacturerPartNumber": "CL10A226MP8NUNE",
  "Manufacturer": "Samsung",
  "Description": "CAP CER 22UF 10V X5R 0603",
  "DataSheetUrl": "https://...",
  "ImagePath": "https://...",
  "Availability": "In Stock",
  "ProductAttributes": [
    {
      "AttributeName": "Capacitance",
      "AttributeValue": "22 µF"
    },
    {
      "AttributeName": "Voltage Rating - DC",
      "AttributeValue": "10 V"
    },
    {
      "AttributeName": "Tolerance",
      "AttributeValue": "±10%"
    },
    {
      "AttributeName": "Dielectric Material",
      "AttributeValue": "X5R"
    },
    {
      "AttributeName": "Package / Case",
      "AttributeValue": "0603 (1608 Metric)"
    },
    {
      "AttributeName": "Mounting Type",
      "AttributeValue": "Surface Mount"
    },
    {
      "AttributeName": "Operating Temperature",
      "AttributeValue": "-55°C ~ 85°C"
    }
  ]
}
```

### Mapping Table

| Mouser Attribute Name | Our Field | Example Value | Notes |
|----------------------|-----------|---------------|-------|
| **Capacitance** | `value` | "22uF" | For capacitors |
| **Resistance** | `value` | "100R", "1K" | For resistors |
| **Inductance** | `value` | "10uH" | For inductors |
| **Tolerance** | `tolerance` | "±5%" | Normalized with ± symbol |
| **Voltage Rating** | `voltage_rating` | "16V" | |
| **Voltage Rating - DC** | `voltage_rating` | "16V" | Alternative naming |
| **DC Voltage Rating** | `voltage_rating` | "16V" | Alternative naming |
| **Current Rating** | `current_rating` | "2A" | |
| **Current - Max** | `current_rating` | "500mA" | Alternative naming |
| **Power Rating** | `power_rating` | "0.25W" | |
| **Power** | `power_rating` | "1/4W" | Alternative naming |
| **Package / Case** | `package_type` | "0805" | Extracted from "0805 (2012 Metric)" |
| **Case/Package** | `package_type` | "SOT-23" | Alternative naming |
| **Mounting Style** | `mounting_type` | "SMD" | Normalized |
| **Mounting Type** | `mounting_type` | "Through Hole" | Normalized |
| **Case Style** | `case_style` | "SOIC-8" | IC packages |
| **Dielectric** | `dielectric` | "X7R" | Capacitors only |
| **Dielectric Material** | `dielectric` | "X5R" | Alternative naming |
| **Temperature Coefficient** | `temperature_coefficient` | "±50ppm/°C" | Precision components |
| **Operating Temperature** | `operating_temperature` | "-40°C ~ +85°C" | Normalized format |
| **Operating Temperature Range** | `operating_temperature` | "-55°C ~ 125°C" | Alternative naming |

### Normalization Rules (Mouser)

```php
// Mounting Type
"Surface Mount" → "SMD"
"Surface Mount Device" → "SMD"
"SMT" → "SMD"
"Through Hole" → "Through Hole"
"Through-Hole" → "Through Hole"
"THT" → "Through Hole"

// Tolerance
"5%" → "±5%"
"±5%" → "±5%" (unchanged)

// Package extraction
"0805 (2012 Metric)" → "0805"
"SOT-23-3" → "SOT-23"
```

## DigiKey API Field Mappings

### DigiKey API Response Structure

```json
{
  "DigiKeyPartNumber": "1276-6717-1-ND",
  "ManufacturerPartNumber": "CL10A226MP8NUNE",
  "Manufacturer": {
    "Value": "Samsung"
  },
  "ProductDescription": "CAP CER 22UF 10V X5R 0603",
  "PrimaryDatasheet": "https://...",
  "PrimaryPhoto": "https://...",
  "QuantityAvailable": 50000,
  "Parameters": [
    {
      "Parameter": "Capacitance",
      "Value": "22µF"
    },
    {
      "Parameter": "Voltage - Rated",
      "Value": "10V"
    },
    {
      "Parameter": "Tolerance",
      "Value": "±10%"
    },
    {
      "Parameter": "Package / Case",
      "Value": "0603 (1608 Metric)"
    },
    {
      "Parameter": "Mounting Type",
      "Value": "Surface Mount, MLCC"
    },
    {
      "Parameter": "Operating Temperature",
      "Value": "-55°C ~ 85°C"
    }
  ]
}
```

### Mapping Table

| DigiKey Parameter Name | Our Field | Example Value | Notes |
|------------------------|-----------|---------------|-------|
| **Capacitance** | `value` | "22µF" | For capacitors |
| **Resistance** | `value` | "100R" | For resistors |
| **Resistance (Ohms)** | `value` | "1K" | Alternative naming |
| **Inductance** | `value` | "10µH" | For inductors |
| **Tolerance** | `tolerance` | "±5%" | Already normalized |
| **Voltage - Rated** | `voltage_rating` | "16V" | |
| **Voltage Rating - DC** | `voltage_rating` | "16V" | Alternative naming |
| **Current Rating** | `current_rating` | "2A" | |
| **Current - Max** | `current_rating` | "500mA" | Alternative naming |
| **Power (Watts)** | `power_rating` | "0.25W" | |
| **Power Rating** | `power_rating` | "1/4W" | Alternative naming |
| **Package / Case** | `package_type` | "0805" | |
| **Supplier Device Package** | `package_type` | "SOT-23" | Alternative naming |
| **Mounting Type** | `mounting_type` | "SMD" | Normalized |
| **Temperature Coefficient** | `temperature_coefficient` | "±50ppm/°C" | |
| **Operating Temperature** | `operating_temperature` | "-40°C ~ +85°C" | |

### Normalization Rules (DigiKey)

```php
// Mounting Type (from Parameters)
"Surface Mount" → "SMD"
"Surface Mount, MLCC" → "SMD"
"Through Hole" → "Through Hole"

// Mounting Type (from Packaging field)
"Tape & Reel" → "SMD" (inference)
"Cut Tape" → "SMD" (inference)
"Tube" → "Through Hole" (inference)
"Bulk" → "Through Hole" (inference)

// µ symbol handling
"22µF" → "22UF"
"10µH" → "10UH"
```

## Description Regex Patterns (Fallback)

### Extraction Patterns

| Pattern Type | Regex | Example Matches | Our Field |
|-------------|-------|-----------------|-----------|
| **Capacitance** | `(\d+\.?\d*)\s*(uf\|µf\|nf\|pf)` | "22UF", "100NF", "10PF" | `value` |
| **Resistance** | `(\d+\.?\d*)\s*(r\|k\|m)?\s*ohm` | "100R", "1K OHM", "10M" | `value` |
| **Inductance** | `(\d+\.?\d*)\s*(uh\|µh\|mh\|nh)` | "10UH", "1MH", "100NH" | `value` |
| **Voltage** | `(\d+\.?\d*)\s*v(dc\|ac)?` | "16V", "3.3VDC", "50VAC" | `voltage_rating` |
| **Current** | `(\d+\.?\d*)\s*(ma\|a)` | "2A", "500MA" | `current_rating` |
| **Power** | `(\d+\/\d+\|\d+\.?\d*)\s*w` | "0.25W", "1/4W" | `power_rating` |
| **Tolerance** | `±?\s*(\d+)\s*%` | "±5%", "1%" | `tolerance` |
| **SMD Package** | `(0201\|0402\|0603\|0805\|1206\|1210\|2010\|2512)` | "0805", "1206" | `package_type` |
| **IC Package** | `(sot-?\d+\|soic-?\d+\|tqfp-?\d+\|qfn-?\d+)` | "SOT-23", "SOIC-8" | `case_style` |
| **Dielectric** | `(x7r\|x5r\|c0g\|np0\|y5v)` | "X7R", "C0G" | `dielectric` |
| **Temperature** | `(-?\d+)\s*[°ºC]?\s*~\s*[\+\-]?(\d+)` | "-40°C ~ +85°C" | `operating_temperature` |
| **Mounting Keyword** | `\b(smd\|smt)\b` | "SMD", "SMT" | `mounting_type` = "SMD" |
| **Through Hole Keyword** | `\b(through hole\|tht)\b` | "THROUGH HOLE" | `mounting_type` = "Through Hole" |

### Example Descriptions & Extracted Values

#### Capacitor Example
```
Input: "CAP CER 22UF 10V X5R 0603 SMD"

Extracted:
- value: "22UF"
- voltage_rating: "10V"
- dielectric: "X5R"
- package_type: "0603"
- mounting_type: "SMD"
```

#### Resistor Example
```
Input: "RES SMD 1K OHM 1% 1/8W 0805"

Extracted:
- value: "1K"
- tolerance: "±1%"
- power_rating: "1/8W"
- package_type: "0805"
- mounting_type: "SMD"
```

#### IC Example
```
Input: "ARM Cortex-M4 MCU 168MHz 1MB Flash LQFP-100 -40°C ~ +85°C"

Extracted:
- case_style: "LQFP-100"
- operating_temperature: "-40°C ~ +85°C"
- mounting_type: "SMD" (inferred from LQFP)
```

## Package Type to Mounting Type Auto-Detection

### SMD Package Patterns

```php
$smdPatterns = [
    // Chip components
    '0201', '0402', '0603', '0805', '1206', '1210', '2010', '2512',

    // IC packages
    'sot', 'soic', 'tqfp', 'qfn', 'dfn', 'bga', 'lga', 'tssop',
    'msop', 'ssop', 'vssop', 'wlcsp',

    // Power packages
    'dpak', 'ddpak', 'to-263', 'to-252',
];

// Logic
if (package contains any of above) → mounting_type = "SMD"
else → mounting_type = "Through Hole"
```

### Through-Hole Package Patterns

```php
$throughHolePatterns = [
    'dip', 'sip', 'to-92', 'to-220', 'to-247',
    'axial', 'radial', 'through hole'
];
```

## Complete Example: Mouser API to Component

### API Response
```json
{
  "MouserPartNumber": "187-CL10A226MP8NUNE",
  "ManufacturerPartNumber": "CL10A226MP8NUNE",
  "Manufacturer": "Samsung",
  "Description": "CAP CER 22UF 10V X5R 0603",
  "DataSheetUrl": "https://example.com/datasheet.pdf",
  "ProductAttributes": [
    {"AttributeName": "Capacitance", "AttributeValue": "22 µF"},
    {"AttributeName": "Voltage Rating - DC", "AttributeValue": "10 V"},
    {"AttributeName": "Tolerance", "AttributeValue": "±10%"},
    {"AttributeName": "Dielectric Material", "AttributeValue": "X5R"},
    {"AttributeName": "Package / Case", "AttributeValue": "0603 (1608 Metric)"},
    {"AttributeName": "Mounting Type", "AttributeValue": "Surface Mount"},
    {"AttributeName": "Operating Temperature", "AttributeValue": "-55°C ~ 85°C"}
  ]
}
```

### Mapped Component Fields
```php
[
    'value' => '22UF',                    // From "Capacitance": "22 µF"
    'voltage_rating' => '10V',            // From "Voltage Rating - DC": "10 V"
    'tolerance' => '±10%',                // From "Tolerance": "±10%"
    'dielectric' => 'X5R',                // From "Dielectric Material": "X5R"
    'package_type' => '0603',             // From "Package / Case": "0603 (1608 Metric)"
    'mounting_type' => 'SMD',             // From "Mounting Type": "Surface Mount" → normalized
    'operating_temperature' => '-55°C ~ +85°C', // From "Operating Temperature"
]
```

### Database Update
```sql
UPDATE components
SET
  value = '22UF',
  voltage_rating = '10V',
  tolerance = '±10%',
  dielectric = 'X5R',
  package_type = '0603',
  mounting_type = 'SMD',
  operating_temperature = '-55°C ~ +85°C',
  datasheet_url = 'https://example.com/datasheet.pdf'
WHERE manufacturer_part_number = 'CL10A226MP8NUNE';
```

## Testing Your Mappings

### Test Script

```php
// Test Mouser mapping
$mouser = app(\App\Services\Suppliers\MouserApiService::class);
$partData = $mouser->getPartDetails('187-CL10A226MP8NUNE');

$scraper = app(\App\Services\DatasheetScraperService::class);
$reflection = new \ReflectionClass($scraper);
$method = $reflection->getMethod('mapMouserAttributesToSpecs');
$method->setAccessible(true);

$specs = $method->invoke($scraper, $partData, $component);
print_r($specs);
```

### Expected Output
```php
Array
(
    [value] => 22UF
    [voltage_rating] => 10V
    [tolerance] => ±10%
    [dielectric] => X5R
    [package_type] => 0603
    [mounting_type] => SMD
    [operating_temperature] => -55°C ~ +85°C
)
```

---

**Last Updated:** October 8, 2025
**Version:** 1.0.0
