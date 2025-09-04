<?php

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Exception;

/**
 * Генератор ценников из Excel файлов
 */
class PriceTagGenerator
{
    private $excelPath;
    private $resultsPath;
    private $templatesPath;
    private $assetsPath;

    public function __construct()
    {
        $this->excelPath = 'excel/';
        $this->resultsPath = 'results/';
        $this->templatesPath = 'templates/';
        $this->assetsPath = 'assets/';
        
        // Создаем директории если их нет
        if (!is_dir($this->resultsPath)) {
            mkdir($this->resultsPath, 0755, true);
        }
    }

    /**
     * Основной метод для генерации всех ценников
     */
    public function generateAll($mode = 'print')
    {
        $excelFiles = [
            'accessories' => 'accessories.xlsx',
            'promotions' => 'promotions.xlsx', 
            'simple' => 'simple.xlsx',
            'simple_accessories' => 'simple_accessories.xlsx'
        ];
        
        if ($mode === 'print') {
            // Только лист для печати
            echo "Создаем лист для печати...\n";
            $this->generatePrintSheets($excelFiles);
        } elseif ($mode === 'list') {
            // Список всех ценников в одном файле
            echo "Создаем список ценников...\n";
            $this->generatePriceTagsList($excelFiles);
        } elseif ($mode === 'simple-list') {
            // Список simple ценников используя оригинальный шаблон
            echo "Создаем список simple ценников используя шаблон...\n";
            $this->generateSimplePriceTagsList($excelFiles['simple']);
        } elseif ($mode === 'promotions-list') {
            // Список promotions ценников используя оригинальный шаблон
            echo "Создаем список promotions ценников используя шаблон...\n";
            $this->generatePromotionsPriceTagsList($excelFiles['promotions']);
        } elseif ($mode === 'accessories-list') {
            // Список accessories ценников используя оригинальный шаблон
            echo "Создаем список accessories ценников используя шаблон...\n";
            $this->generateAccessoriesPriceTagsList($excelFiles['accessories']);
        } elseif ($mode === 'simple-accessories-list') {
            // Список simple_accessories ценников используя оригинальный шаблон
            echo "Создаем список simple_accessories ценников используя шаблон...\n";
            $this->generateSimpleAccessoriesPriceTagsList($excelFiles['simple_accessories']);
        } else {
            // Только отдельные ценники
            foreach ($excelFiles as $type => $filename) {
                echo "Обрабатываем файл: {$filename}\n";
                $this->processExcelFile($type, $filename);
            }
        }
        
        echo "Генерация ценников завершена!\n";
    }

    /**
     * Обработка Excel файла
     */
    private function processExcelFile($type, $filename)
    {
        $filePath = $this->excelPath . $filename;
        
        if (!file_exists($filePath)) {
            echo "Файл {$filename} не найден\n";
            return;
        }
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $this->parseExcelData($worksheet);
            
            $outputDir = $this->resultsPath . $type . '/';
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }
            
            foreach ($data as $index => $item) {
                $html = $this->generateSinglePriceTag($type, $item);
                $filename = $this->generateFilename($item, $index);
                $filePath = $outputDir . $filename;
                
                file_put_contents($filePath, $html);
                echo "Создан ценник: {$filename}\n";
            }
            
            // Копируем изображения
            $this->copyImagesToResults($outputDir);
            
        } catch (Exception $e) {
            echo "Ошибка при обработке файла {$filename}: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Парсинг данных из Excel
     */
    private function parseExcelData($worksheet)
    {
        $data = [];
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        
        // Получаем заголовки
        $headers = [];
        for ($col = 'A'; $col <= $highestColumn; $col++) {
            $headers[$col] = $worksheet->getCell($col . '1')->getValue();
        }
        
        // Парсим данные
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            foreach ($headers as $col => $header) {
                if ($header) {
                    $rowData[$header] = $worksheet->getCell($col . $row)->getValue();
                }
            }
            
            // Проверяем, что строка не пустая
            if (!empty(array_filter($rowData, function($value) {
                return $value !== null && $value !== '';
            }))) {
                $data[] = $rowData;
            }
        }
        
        return $data;
    }

    /**
     * Генерация HTML ценника
     */
    private function generateSinglePriceTag($type, $item)
    {
        $templateFile = $this->templatesPath . $type . '/index.html';
        
        if (!file_exists($templateFile)) {
            return $this->generateSimplePriceTag($type, $item);
        }
        
        $html = file_get_contents($templateFile);
        
        // Заполняем шаблон данными
        switch ($type) {
            case 'accessories':
                $html = $this->fillAccessoriesTemplate($html, $item);
                break;
            case 'promotions':
                $html = $this->fillPromotionsTemplate($html, $item);
                break;
            case 'simple':
                $html = $this->fillSimpleTemplate($html, $item);
                break;
            case 'simple_accessories':
                $html = $this->fillSimpleAccessoriesTemplate($html, $item);
                break;
        }
        
        // Встраиваем CSS стили
        $html = $this->inlineCssStyles($html, $type);
        
        // Обновляем пути к изображениям
        $html = $this->updateImagePaths($html);
        
        return $html;
    }

    /**
     * Заполнение шаблона аксессуаров
     */
    private function fillAccessoriesTemplate($html, $item)
    {
        $replacements = [
            '{{Название}}' => isset($item['Название']) ? htmlspecialchars($item['Название']) : '',
            '{{Цена}}' => isset($item['Цена']) ? $this->formatPrice($item['Цена']) : '',
            '{{Старая цена}}' => isset($item['Старая цена']) ? $this->formatPrice($item['Старая цена']) : '',
            '{{Рассрочка}}' => isset($item['Рассрочка']) ? htmlspecialchars($item['Рассрочка']) : ''
        ];
        
        // Сначала пытаемся заменить плейсхолдеры, если они есть в шаблоне
        $htmlAfterPlaceholders = str_replace(array_keys($replacements), array_values($replacements), $html);

        // Определяем, были ли плейсхолдеры в шаблоне
        $placeholdersWereUsed = $htmlAfterPlaceholders !== $html;

        if ($placeholdersWereUsed) {
            $html = $htmlAfterPlaceholders;

            // Убираем блоки с пустыми данными (по классам old-price / installment)
        if (empty($item['Старая цена'])) {
            $html = preg_replace('/<div[^>]*class="[^"]*old-price[^"]*"[^>]*>.*?<\/div>/s', '', $html);
        }
        if (empty($item['Рассрочка'])) {
            $html = preg_replace('/<div[^>]*class="[^"]*installment[^"]*"[^>]*>.*?<\/div>/s', '', $html);
            }

            return $html;
        }

        // Если в шаблоне НЕТ плейсхолдеров (как в текущем templates/accessories),
        // выполняем точечные подстановки по текстовым узлам шаблона

        // Название товара — заменяем содержимое заголовка
        if (!empty($item['Название'])) {
            $productName = htmlspecialchars($item['Название']);
            $html = preg_replace(
                '/(<p\s+class="text-3">\s*<span\s+class="text-white">)(.*?)(<\/span>\s*<\/p>)/su',
                '$1' . preg_quote($productName, '/') . '$3',
                $html
            );
        }

        // Текущая цена — блок <p class="text-5"><span class="text-white">3 600 000 сум</span></p>
        if (!empty($item['Цена'])) {
            $currentPrice = $this->formatPrice($item['Цена']);
            $html = preg_replace(
                '/(<p\s+class="text-5">\s*<span\s+class="text-white">)(.*?)(<\/span>\s*<\/p>)/su',
                '$1' . preg_quote($currentPrice, '/') . '$3',
                $html
            );
        }

        // Старая цена — блок с классом frame-17-6, внутри <p class="text-7">2 400 000 сум</p>
        if (!empty($item['Старая цена'])) {
            $oldPrice = $this->formatPrice($item['Старая цена']);
            $html = preg_replace(
                '/(<div[^>]*class="[^"]*frame-17-6[^"]*"[^>]*>.*?<p\s+class="text-7"><span\s+class="text-white">)(.*?)(<\/span><\/p>.*?<\/div>)/su',
                '$1' . preg_quote($oldPrice, '/') . '$3',
                $html
            );
        } else {
            // Если старой цены нет — удаляем весь блок frame-17-6
            $html = preg_replace('/<div[^>]*class="[^"]*frame-17-6[^"]*"[^>]*>.*?<\/div>/su', '', $html);
        }

        // Рассрочка — блок frame-15-8, внутри <p class="text-9">от 250 000 сум/мес</p>
        if (!empty($item['Рассрочка'])) {
            $installmentRaw = htmlspecialchars($item['Рассрочка']);
            $html = preg_replace(
                '/(<div[^>]*class="[^"]*frame-15-8[^"]*"[^>]*>.*?<p\s+class="text-9"><span\s+class="text-white">)(.*?)(<\/span><\/p>.*?<\/div>)/su',
                '$1' . preg_quote($installmentRaw, '/') . '$3',
                $html
            );
        } else {
            // Если рассрочки нет — удаляем блок frame-15-8
            $html = preg_replace('/<div[^>]*class="[^"]*frame-15-8[^"]*"[^>]*>.*?<\/div>/su', '', $html);
        }
        
        return $html;
    }

    /**
     * Заполнение шаблона simple_accessories
     */
    private function fillSimpleAccessoriesTemplate($html, $item)
    {
        // Простая замена плейсхолдеров для simple_accessories
        $replacements = [
            '{{Название}}' => isset($item['Название']) ? htmlspecialchars($item['Название']) : 'Название товара',
            '{{Цена}}' => isset($item['Цена']) ? $this->formatPrice($item['Цена']) : '—'
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }

    /**
     * Заполнение шаблона акций
     */
    private function fillPromotionsTemplate($html, $item)
    {
        $replacements = [
            '{{Название товара}}' => isset($item['Название товара']) ? htmlspecialchars($item['Название товара']) : '',
            '{{Камера }}' => isset($item['Камера ']) ? htmlspecialchars($item['Камера ']) : '',
            '{{Дисплей}}' => isset($item['Дисплей']) ? htmlspecialchars($item['Дисплей']) : '',
            '{{Батарея}}' => isset($item['Батарея']) ? htmlspecialchars($item['Батарея']) : '',
            '{{Память}}' => isset($item['Память']) ? htmlspecialchars($item['Память']) : '',
            '{{Старая Цена}}' => isset($item['Старая Цена']) ? $this->formatPrice($item['Старая Цена']) : '',
            '{{Цена без рассрочки}}' => isset($item['Цена без рассрочки']) ? $this->formatPrice($item['Цена без рассрочки']) : '',
            '{{Цена с рассрочкой}}' => isset($item['Цена с рассрочкой']) ? $this->formatPrice($item['Цена с рассрочкой']) : ''
        ];
        
        $htmlAfterPlaceholders = str_replace(array_keys($replacements), array_values($replacements), $html);
        $placeholdersWereUsed = $htmlAfterPlaceholders !== $html;
        
        if ($placeholdersWereUsed) {
            $html = $htmlAfterPlaceholders;

        if (empty($item['Старая Цена'])) {
            $html = preg_replace('/<div[^>]*class="[^"]*old-price[^"]*"[^>]*>.*?<\/div>/s', '', $html);
        }
        if (empty($item['Цена с рассрочкой'])) {
            $html = preg_replace('/<div[^>]*class="[^"]*installment[^"]*"[^>]*>.*?<\/div>/s', '', $html);
            }
            return $html;
        }

        // Без плейсхолдеров — замены по тексту шаблона
        if (!empty($item['Название товара'])) {
            $name = htmlspecialchars($item['Название товара']);
            $html = preg_replace(
                '/(<p\s+class="text-7"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . preg_quote($name, '/') . '$3',
                $html
            );
        }

        // Характеристики
        if (!empty($item['Камера '])) {
            $camera = htmlspecialchars($item['Камера ']);
            $html = preg_replace(
                '/(<p\s+class="text-16"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . preg_quote($camera, '/') . '$3',
                $html,
                1
            );
        }
        if (!empty($item['Дисплей'])) {
            $display = htmlspecialchars($item['Дисплей']);
            $html = preg_replace(
                '/(<p\s+class="text-22"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . preg_quote($display, '/') . '$3',
                $html,
                1
            );
        }
        if (!empty($item['Батарея'])) {
            $battery = htmlspecialchars($item['Батарея']);
            $html = preg_replace(
                '/(<p\s+class="text-29"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . preg_quote($battery, '/') . '$3',
                $html,
                1
            );
        }
        if (!empty($item['Память'])) {
            $memory = htmlspecialchars($item['Память']);
            $html = preg_replace(
                '/(<p\s+class="text-35"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . preg_quote($memory, '/') . '$3',
                $html,
                1
            );
        }

        // Цены
        if (!empty($item['Старая Цена'])) {
            $oldPrice = $this->formatPrice($item['Старая Цена']);
            // в шаблоне старая цена отображается в span.old-price
            $html = preg_replace(
                '/(<span\s+class="old-price">)(.*?)(<\/span>)/su',
                '$1' . preg_quote($oldPrice, '/') . '$3',
                $html
            );
        } else {
            // удалить span.old-price
            $html = preg_replace('/<span\s+class="old-price">.*?<\/span>/su', '', $html);
        }

        if (!empty($item['Цена без рассрочки'])) {
            $price = $this->formatPrice($item['Цена без рассрочки']);
            $html = preg_replace(
                '/(<p\s+class="text-47"><span\s+class="text-white">)(.*?)(<\/span><\/p>)/su',
                '$1' . preg_quote($price, '/') . '$3',
                $html,
                1
            );
        }

        if (!empty($item['Цена с рассрочкой'])) {
            $installment = $this->formatPrice($item['Цена с рассрочкой']);
            $html = preg_replace(
                '/(<p\s+class="text-43"><span\s+class="text-white">)(.*?)(<\/span><\/p>)/su',
                '$1' . preg_quote($installment, '/') . '$3',
                $html,
                1
            );
        } else {
            // убрать блок с рассрочкой: это div.frame-17-38 содержащий frame-22-39
            $html = preg_replace('/<div[^>]*class="[^"]*frame-17-38[^"]*"[^>]*>.*?<\/div>/su', '', $html);
        }
        
        return $html;
    }

    /**
     * Заполнение простого шаблона
     */
    protected function fillSimpleTemplate($html, $item)
    {
        
        // Заменяем содержимое по CSS классам для надежности
        
        // Название товара - заменяем содержимое в text-7
        if (isset($item['Название товара']) && !empty($item['Название товара'])) {
            $html = preg_replace(
                '/(<p\s+class="text-7"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . htmlspecialchars($item['Название товара']) . '$3',
                $html
            );
        }
        
        // Характеристики - заменяем по классам
        if (isset($item['Камера ']) && !empty($item['Камера '])) {
            $html = preg_replace(
                '/(<p\s+class="text-16"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . htmlspecialchars($item['Камера ']) . '$3',
                $html
            );
        }
        
        if (isset($item['Дисплей']) && !empty($item['Дисплей'])) {
            $html = preg_replace(
                '/(<p\s+class="text-22"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . htmlspecialchars($item['Дисплей']) . '$3',
                $html
            );
        }
        
        if (isset($item['Батарея']) && !empty($item['Батарея'])) {
            $html = preg_replace(
                '/(<p\s+class="text-29"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . htmlspecialchars($item['Батарея']) . '$3',
                $html
            );
        }
        
        if (isset($item['Память']) && !empty($item['Память'])) {
            $html = preg_replace(
                '/(<p\s+class="text-35"><span\s+class="text-rgb-30-30-30">)(.*?)(<\/span><\/p>)/su',
                '$1' . htmlspecialchars($item['Память']) . '$3',
                $html
            );
        }
        
        // Сначала заменяем старую цену в span.old-price на временное значение
        if (isset($item['Старая Цена']) && !empty($item['Старая Цена'])) {
            $oldPrice = $this->formatPrice($item['Старая Цена']);
            // Заменяем содержимое в span.old-price на временное значение
            $html = str_replace('<span class="old-price">12 000 000 сум</span>', '<span class="old-price">TEMP_OLD_PRICE</span>', $html);
        } else {
            // Удаляем span.old-price если старая цена не указана
            $html = str_replace('<span class="old-price">12 000 000 сум</span>', '', $html);
        }
        
        // Затем заменяем остальные цены - заменяем ВСЕ вхождения статического текста глобально
        if (isset($item['Цена без рассрочки']) && !empty($item['Цена без рассрочки'])) {
            $price = $this->formatPrice($item['Цена без рассрочки']);
            // Заменяем ВСЕ вхождения статического текста "12 000 000 сум" глобально
            $html = str_replace('12 000 000 сум', $price, $html);
        }
        
        // В конце заменяем временное значение на реальную старую цену
        if (isset($item['Старая Цена']) && !empty($item['Старая Цена'])) {
            $oldPrice = $this->formatPrice($item['Старая Цена']);
            $html = str_replace('<span class="old-price">TEMP_OLD_PRICE</span>', '<span class="old-price">' . $oldPrice . '</span>', $html);
        }
        
        if (isset($item['Цена с рассрочкой']) && !empty($item['Цена с рассрочкой'])) {
            $installmentPrice = $this->formatPrice($item['Цена с рассрочкой']);
            // Заменяем ВСЕ вхождения статического текста "1 130 000" глобально
            $html = str_replace('1 130 000', $installmentPrice, $html);
        }
        
        return $html;
    }

    /**
     * Генерация простого ценника если шаблон не найден
     */
    private function generateSimplePriceTag($type, $item)
    {
        $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ценник</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .price-tag { border: 2px solid #333; padding: 20px; max-width: 300px; }
        .name { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
        .price { font-size: 24px; color: #e74c3c; font-weight: bold; }
        .specs { margin: 10px 0; font-size: 14px; }
    </style>
</head>
<body>
    <div class="price-tag">';
        
        if (isset($item['Название товара'])) {
            $html .= '<div class="name">' . htmlspecialchars($item['Название товара']) . '</div>';
        } elseif (isset($item['Название'])) {
            $html .= '<div class="name">' . htmlspecialchars($item['Название']) . '</div>';
        }
        
        if (isset($item['Цена без рассрочки'])) {
            $html .= '<div class="price">' . $this->formatPrice($item['Цена без рассрочки']) . '</div>';
        } elseif (isset($item['Цена'])) {
            $html .= '<div class="price">' . $this->formatPrice($item['Цена']) . '</div>';
        }
        
        $html .= '</div>
</body>
</html>';
        
        return $html;
    }

    /**
     * Форматирование цены
     */
    protected function formatPrice($price)
    {
        // Убираем "сум" если есть, чтобы обработать только число
        $price = str_replace(' сум', '', $price);
        $price = trim($price);
        
        // Если это число, форматируем его с пробелами
        if (is_numeric($price)) {
            $price = number_format($price, 0, '.', ' ');
        }
        
        // Добавляем "сум" обратно
        $price .= ' сум';
        
        return $price;
    }

    /**
     * Встраивание CSS стилей в HTML
     */
    private function inlineCssStyles($html, $type)
    {
        $cssFile = $this->templatesPath . $type . '/styles.css';
        
        if (file_exists($cssFile)) {
            $css = file_get_contents($cssFile);
            $html = str_replace('</head>', '<style>' . $css . '</style></head>', $html);
        }
        
        return $html;
    }

    /**
     * Обновление путей к изображениям
     */
    private function updateImagePaths($html)
    {
        // Заменяем пути к изображениям на относительные
        $html = str_replace('../assets/images/', 'images/', $html);
        $html = str_replace('assets/images/', 'images/', $html);
        
        return $html;
    }
    
    /**
     * Обновление путей к изображениям для печати
     */
    private function updateImagePathsForPrint($html)
    {
        // Для печати используем абсолютные пути к изображениям в assets
        $html = str_replace('images/', 'assets/images/', $html);
        
        return $html;
    }

    /**
     * Копирование изображений в папку результатов
     */
    private function copyImagesToResults($outputDir)
    {
        $imagesDir = $outputDir . 'images/';
        if (!is_dir($imagesDir)) {
            mkdir($imagesDir, 0755, true);
        }
        
        $sourceImagesDir = $this->assetsPath . 'images/';
        if (is_dir($sourceImagesDir)) {
            $this->copyDirectory($sourceImagesDir, $imagesDir);
        }
    }

    /**
     * Копирование директории
     */
    private function copyDirectory($source, $destination)
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $dir = opendir($source);
        while (($file = readdir($dir)) !== false) {
            if ($file != '.' && $file != '..') {
                $sourcePath = $source . $file;
                $destPath = $destination . $file;
                
                if (is_dir($sourcePath)) {
                    $this->copyDirectory($sourcePath, $destPath);
                } else {
                    copy($sourcePath, $destPath);
                }
            }
        }
        closedir($dir);
    }

    /**
     * Генерация листов для печати
     */
    private function generatePrintSheets($excelFiles)
    {
        // Генерируем отдельный лист печати для каждого типа товаров
        foreach ($excelFiles as $type => $filename) {
            echo "Создаем лист для печати {$type}...\n";
            $this->generatePrintSheetForType($type, $filename);
        }
    }
    
    /**
     * Генерация листа печати для конкретного типа товаров
     */
    private function generatePrintSheetForType($type, $filename)
    {
        $filePath = $this->excelPath . $filename;
        
        if (!file_exists($filePath)) {
            echo "Файл {$filename} не найден\n";
            return;
        }
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $this->parseExcelData($worksheet);
            
            if (empty($data)) {
                echo "Нет данных для типа {$type}\n";
                return;
            }
            
            // Группируем товары по 4 штуки (2×2 сетка)
            $itemsPerPage = 4;
            $pages = array_chunk($data, $itemsPerPage);
            
            // Создаем HTML для печати с использованием шаблона
            $printHtml = $this->createPrintHtmlWithTemplate($type, $pages);
            
            // Создаем папку для типа товаров
            $outputDir = $this->resultsPath . $type . '/';
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }
            
            // Сохраняем в файл
            $printFile = $outputDir . 'print_sheet.html';
            file_put_contents($printFile, $printHtml);
            
            echo "Создан лист для печати {$type}: {$printFile}\n";
            echo "Всего страниц: " . count($pages) . "\n";
            
        } catch (Exception $e) {
            echo "Ошибка при обработке файла {$filename}: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Создание HTML для печати с использованием шаблонов
     */
    private function createPrintHtmlWithTemplate($type, $pages)
    {
        // Загружаем HTML и CSS из оригинальных шаблонов
        $htmlTemplateFile = $this->templatesPath . $type . '/index.html';
        $cssFile = $this->templatesPath . $type . '/styles.css';
        
        if (!file_exists($htmlTemplateFile)) {
            return $this->createPrintHtmlFallback($type, $pages);
        }
        
        $htmlTemplate = file_get_contents($htmlTemplateFile);
        $css = '';
        if (file_exists($cssFile)) {
            $css = file_get_contents($cssFile);
        }
        
        // Создаем HTML для печати на основе оригинального шаблона
        $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Лист для печати ценников - ' . ucfirst($type) . '</title>
    <link href="https://fonts.googleapis.com/css?family=Inter&display=swap" rel="stylesheet">
    <style>
        @media print {
            body { margin: 0; }
            .page { page-break-after: always; }
            .page:last-child { page-break-after: avoid; }
        }
        
        body {
            margin: 0;
            padding: 20px;
            font-family: "Inter", sans-serif;
            background: white;
        }
        
        .page {
            width: 794px;
            min-height: 1123px;
            margin: 0 auto 20px auto;
            border: 1px solid #ccc;
            padding: 20px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            gap: 20px;
            align-items: center;
        }
        
        .price-tag-row {
            display: flex;
            gap: 20px;
            justify-content: center;
            width: 100%;
        }
        
        .price-tag {
            width: 264px;
            height: auto;
            min-height: 378px;
            border-radius: 8px;
            padding: 15px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            overflow: visible;
            position: relative;
            background: white;
            border: 1px solid #eee;
            flex-shrink: 0;
        }
        
        .page-info {
            text-align: center;
            font-size: 12px;
            color: #666;
            margin-top: 10px;
        }
        
        ' . $css . '
    </style>
</head>
<body>';
        
                // Создаем страницы с ценниками
        foreach ($pages as $pageIndex => $pageItems) {
            $html .= '<div class="page">';

            // Группируем ценники по 2 в ряд
            for ($i = 0; $i < count($pageItems); $i += 2) {
                $html .= '<div class="price-tag-row">';
                
                // Первый ценник в ряду
                $html .= $this->createPriceTagFromOriginalTemplate($type, $pageItems[$i], $htmlTemplate);
                
                // Второй ценник в ряду (если есть)
                if ($i + 1 < count($pageItems)) {
                    $html .= $this->createPriceTagFromOriginalTemplate($type, $pageItems[$i + 1], $htmlTemplate);
                }
                
                $html .= '</div>';
            }

            // Добавляем информацию о странице
            $html .= '<div class="page-info">Страница ' . ($pageIndex + 1) . '</div>';
            $html .= '</div>';
        }
        
        $html .= '</body></html>';
        
        return $html;
    }
    
    /**
     * Fallback метод для создания HTML печати
     */
    private function createPrintHtmlFallback($type, $pages)
    {
        $cssFile = $this->templatesPath . $type . '/styles.css';
        $css = '';
        if (file_exists($cssFile)) {
            $css = file_get_contents($cssFile);
        }
        
        $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Лист для печати ценников - ' . ucfirst($type) . '</title>
    <style>
        @media print {
            body { margin: 0; }
            .page { page-break-after: always; }
            .page:last-child { page-break-after: avoid; }
        }
        
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: white;
        }
        
        .page {
            width: 794px;
            min-height: 1123px;
            margin: 0 auto 20px auto;
            border: 1px solid #ccc;
            padding: 20px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            gap: 20px;
            align-items: center;
        }
        
        .price-tag-row {
            display: flex;
            gap: 20px;
            justify-content: center;
            width: 100%;
        }
        
        .price-tag {
            width: 264px;
            height: auto;
            min-height: 378px;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 15px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            background: white;
            overflow: visible;
            flex-shrink: 0;
        }
        
        .page-info {
            text-align: center;
            font-size: 12px;
            color: #666;
            margin-top: 10px;
        }
        
        ' . $css . '
    </style>
</head>
<body>';
        
        foreach ($pages as $pageIndex => $pageItems) {
            $html .= '<div class="page">';
            
            foreach ($pageItems as $item) {
                $html .= $this->createPriceTagFromTemplate($type, $item);
            }
            
            // Добавляем информацию о странице
            $html .= '<div class="page-info">Страница ' . ($pageIndex + 1) . '</div>';
            $html .= '</div>';
        }
        
        $html .= '</body></html>';
        
        return $html;
    }

    /**
     * Создание ценника из шаблона для печати
     */
    private function createPriceTagFromTemplate($type, $item)
    {
        $templateFile = $this->templatesPath . $type . '/index.html';
        
        if (!file_exists($templateFile)) {
            return $this->createCompactPriceTag($item);
        }
        
        $html = file_get_contents($templateFile);
        
        // Заполняем шаблон данными
        switch ($type) {
            case 'accessories':
                $html = $this->fillAccessoriesTemplate($html, $item);
                break;
            case 'promotions':
                $html = $this->fillPromotionsTemplate($html, $item);
                break;
            case 'simple':
                $html = $this->fillSimpleTemplate($html, $item);
                break;
        }
        
        // Обновляем пути к изображениям для печати
        $html = $this->updateImagePathsForPrint($html);
        
        // Оборачиваем в контейнер для печати
        return '<div class="price-tag">' . $html . '</div>';
    }
    
    /**
     * Создание ценника из оригинального шаблона для печати
     */
    private function createPriceTagFromOriginalTemplate($type, $item, $htmlTemplate)
    {
        // Копируем HTML шаблон
        $html = $htmlTemplate;
        
        // Заполняем шаблон данными
        switch ($type) {
            case 'accessories':
                $html = $this->fillAccessoriesTemplate($html, $item);
                break;
            case 'promotions':
                $html = $this->fillPromotionsTemplate($html, $item);
                break;
            case 'simple':
                $html = $this->fillSimpleTemplate($html, $item);
                break;
        }
        
        // Обновляем пути к изображениям для печати
        $html = $this->updateImagePathsForPrint($html);
        
        // Убираем лишние теги body и html, оставляем только содержимое
        $html = preg_replace('/<body[^>]*>(.*)<\/body>/s', '$1', $html);
        $html = preg_replace('/<html[^>]*>(.*)<\/html>/s', '$1', $html);
        $html = preg_replace('/<head>.*<\/head>/s', '', $html);
        
        // Оборачиваем в контейнер для печати
        return '<div class="price-tag">' . $html . '</div>';
    }
    

    

    

    

    
    /**
     * Создание компактного ценника для печати
     */
    private function createCompactPriceTag($item)
    {
        $html = '<div class="price-tag">';
        
        // Бейдж акции (если есть тип)
        if (isset($item['type']) && $item['type'] === 'promotions') {
            $html .= '<div class="promotion-badge">АКЦИЯ</div>';
        }
        
        // Название товара
        if (isset($item['Название товара'])) {
            $html .= '<div class="product-name">' . htmlspecialchars($item['Название товара']) . '</div>';
        } elseif (isset($item['Название'])) {
            $html .= '<div class="product-name">' . htmlspecialchars($item['Название']) . '</div>';
        }
        
        // Характеристики
        $specs = [];
        if (isset($item['Камера ']) && !empty($item['Камера '])) {
            $specs[] = 'Камера: ' . htmlspecialchars($item['Камера ']);
        }
        if (isset($item['Дисплей']) && !empty($item['Дисплей'])) {
            $specs[] = 'Дисплей: ' . htmlspecialchars($item['Дисплей']);
        }
        if (isset($item['Батарея']) && !empty($item['Батарея'])) {
            $specs[] = 'Батарея: ' . htmlspecialchars($item['Батарея']);
        }
        if (isset($item['Память']) && !empty($item['Память'])) {
            $specs[] = 'Память: ' . htmlspecialchars($item['Память']);
        }
        
        if (!empty($specs)) {
            $html .= '<div class="product-description">' . implode('<br>', $specs) . '</div>';
        }
        
        // Цены
        if (isset($item['Цена без рассрочки']) && !empty($item['Цена без рассрочки'])) {
            $price = htmlspecialchars($item['Цена без рассрочки']);
            if (!str_contains($price, 'сум')) {
                $price .= ' сум';
            }
            $html .= '<div class="price">' . $price . '</div>';
        } elseif (isset($item['Цена']) && !empty($item['Цена'])) {
            $price = htmlspecialchars($item['Цена']);
            if (!str_contains($price, 'сум')) {
                $price .= ' сум';
            }
            $html .= '<div class="price">' . $price . '</div>';
        }
        
        // Старая цена для акций
        if ($item['type'] === 'promotions' && isset($item['Старая Цена']) && !empty($item['Старая Цена'])) {
            $oldPrice = htmlspecialchars($item['Старая Цена']);
            if (!str_contains($oldPrice, 'сум')) {
                $oldPrice .= ' сум';
            }
            $html .= '<div class="old-price">' . $oldPrice . '</div>';
        }
        
        // ID товара
        if (isset($item['ID товара (QR Code)'])) {
            $html .= '<div class="barcode">ID: ' . htmlspecialchars($item['ID товара (QR Code)']) . '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Генерация имени файла
     */
    private function generateFilename($item, $index)
    {
        $name = 'price_tag';
        
        if (isset($item['Название'])) {
            $name = $this->sanitizeFilename($item['Название']);
        } elseif (isset($item['Название товара'])) {
            $name = $this->sanitizeFilename($item['Название товара']);
        } elseif (isset($item['ID товара (QR Code)'])) {
            $name = 'id_' . $this->sanitizeFilename($item['ID товара (QR Code)']);
        } elseif (isset($item['Артикул'])) {
            $name = 'art_' . $this->sanitizeFilename($item['Артикул']);
        }
        
        return $name . '_' . ($index + 1) . '.html';
    }

    /**
     * Очистка имени файла от недопустимых символов
     */
    private function sanitizeFilename($filename)
    {
        $filename = preg_replace('/[^a-zA-Z0-9а-яА-Я\s\-_]/u', '', $filename);
        $filename = preg_replace('/\s+/', '_', $filename);
        return substr($filename, 0, 50);
    }

    /**
     * Генерация списка всех ценников в одном файле
     */
    private function generatePriceTagsList($excelFiles)
    {
        $allPriceTags = [];
        
        // Собираем все ценники из всех Excel файлов
        foreach ($excelFiles as $type => $filename) {
            $filePath = $this->excelPath . $filename;
            
            if (!file_exists($filePath)) {
                echo "Файл {$filename} не найден\n";
                continue;
            }
            
            try {
                $spreadsheet = IOFactory::load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $data = $this->parseExcelData($worksheet);
                
                foreach ($data as $item) {
                    $item['type'] = $type; // Добавляем тип для идентификации
                    $allPriceTags[] = $item;
                }
                
                echo "Загружено {$type}: " . count($data) . " товаров\n";
                
            } catch (Exception $e) {
                echo "Ошибка при обработке файла {$filename}: " . $e->getMessage() . "\n";
            }
        }
        
        if (empty($allPriceTags)) {
            echo "Нет данных для генерации списка ценников\n";
            return;
        }
        
        // Создаем HTML файл со списком всех ценников
        $html = $this->createPriceTagsListHtml($allPriceTags);
        
        // Сохраняем в файл
        $outputFile = $this->resultsPath . 'price_tags_list.html';
        file_put_contents($outputFile, $html);
        
        echo "Создан список ценников: {$outputFile}\n";
        echo "Всего товаров: " . count($allPriceTags) . "\n";
    }
    
    /**
     * Создание HTML для списка ценников
     */
    private function createPriceTagsListHtml($priceTags)
    {
        $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список всех ценников</title>
    <link href="https://fonts.googleapis.com/css?family=Inter&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 20px;
            font-family: "Inter", sans-serif;
            background: #f5f5f5;
        }
        
        .lst-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            max-width: 1120px;
            margin: 0 auto;
        }
        
        .lst-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(264px, 1fr));
            gap: 20px;
            width: 100%;
            align-items: start;
        }
        
        .lst-item {
            background: white;
            border-radius: 8px;
            border: 1px solid #e8e8e8;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 540px;
        }
        
        .lst-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .lst-image {
            width: 60px;
            height: 60px;
            border-radius: 4px;
        }
        
        .lst-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e1e1e;
            margin: 0;
        }
        
        .lst-specs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .lst-spec-item {
            background: #fafafa;
            padding: 8px;
            border-radius: 4px;
            text-align: center;
        }
        
        .lst-spec-label {
            font-size: 10px;
            color: #6b6b6b;
            margin-bottom: 2px;
        }
        
        .lst-spec-value {
            font-size: 12px;
            font-weight: 500;
            color: #1e1e1e;
        }
        
        .lst-prices {
            background: linear-gradient(180deg, #652D86 0%, #550981 100%);
            border-radius: 6px;
            padding: 15px;
            color: white;
        }
        
        .lst-installment {
            margin-bottom: 10px;
        }
        
        .lst-installment-label {
            font-size: 9px;
            font-style: italic;
            margin-bottom: 5px;
        }
        
        .lst-installment-value {
            font-size: 20px;
            font-weight: 700;
        }
        
        .lst-regular {
            font-size: 17px;
            font-weight: 500;
        }
        
        .lst-type {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #652D86;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .lst-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="lst-container">
        <h1>Список всех ценников</h1>
        <div class="lst-grid">';
        
        foreach ($priceTags as $item) {
            $html .= $this->createPriceTagItemHtml($item);
        }
        
        $html .= '</div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Создание HTML для одного ценника в списке
     */
    private function createPriceTagItemHtml($item)
    {
        $type = $item['type'] ?? 'simple';
        $typeLabel = [
            'simple' => 'Обычный',
            'promotions' => 'Акция',
            'accessories' => 'Аксессуар'
        ][$type] ?? 'Товар';
        
        $html = '<div class="lst-item" style="position: relative;">
            <div class="lst-type">' . $typeLabel . '</div>
            <div class="lst-header">
                <img src="../assets/images/node-4.svg" class="lst-image" alt="Товар" />
                <h3 class="lst-title">';
        
        // Название товара
        if (isset($item['Название товара'])) {
            $html .= htmlspecialchars($item['Название товара']);
        } elseif (isset($item['Название'])) {
            $html .= htmlspecialchars($item['Название']);
        } else {
            $html .= 'Название не указано';
        }
        
        $html .= '</h3>
            </div>
            <div class="lst-specs">';
        
        // Характеристики
        $specs = [];
        if (isset($item['Камера ']) && !empty($item['Камера '])) {
            $specs[] = ['Камера', $item['Камера ']];
        }
        if (isset($item['Дисплей']) && !empty($item['Дисплей'])) {
            $specs[] = ['Дисплей', $item['Дисплей']];
        }
        if (isset($item['Батарея']) && !empty($item['Батарея'])) {
            $specs[] = ['Батарея', $item['Батарея']];
        }
        if (isset($item['Память']) && !empty($item['Память'])) {
            $specs[] = ['Память', $item['Память']];
        }
        
            foreach ($specs as $spec) {
            $html .= '<div class="lst-spec-item">
                <div class="lst-spec-label">' . htmlspecialchars($spec[0]) . '</div>
                <div class="lst-spec-value">' . htmlspecialchars($spec[1]) . '</div>
            </div>';
        }
        
        $html .= '</div>
            <div class="lst-prices">';
        
        // Цены
        if (isset($item['Цена с рассрочкой']) && !empty($item['Цена с рассрочкой'])) {
            $html .= '<div class="lst-installment">
                <div class="lst-installment-label">Цена в рассрочку:</div>
                <div class="lst-installment-value">от ' . $this->formatPrice($item['Цена с рассрочкой']) . '/мес</div>
            </div>';
        }
        
        if (isset($item['Цена без рассрочки']) && !empty($item['Цена без рассрочки'])) {
            $html .= '<div class="lst-regular">' . $this->formatPrice($item['Цена без рассрочки']) . '</div>';
        } elseif (isset($item['Цена']) && !empty($item['Цена'])) {
            $html .= '<div class="lst-regular">' . $this->formatPrice($item['Цена']) . '</div>';
        }
        
        $html .= '</div>
        </div>';
        
        return $html;
    }

    /**
     * Генерация списка simple ценников используя оригинальный шаблон
     */
    private function generateSimplePriceTagsList($filename)
    {
        $filePath = $this->excelPath . $filename;
        
        if (!file_exists($filePath)) {
            echo "Файл {$filename} не найден\n";
            return;
        }
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $this->parseExcelData($worksheet);
            
            if (empty($data)) {
                echo "Нет данных для генерации simple ценников\n";
                return;
            }
            
            echo "Загружено simple: " . count($data) . " товаров\n";
            
            // Создаем HTML используя оригинальный шаблон
            $html = $this->createSimplePriceTagsListHtml($data);
            
            // Сохраняем в файл
            $outputFile = $this->resultsPath . 'simple/simple_price_tags_list.html';
            file_put_contents($outputFile, $html);
            
            echo "Создан список simple ценников: {$outputFile}\n";
            echo "Всего товаров: " . count($data) . "\n";
            
        } catch (Exception $e) {
            echo "Ошибка при обработке файла {$filename}: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Создание HTML для списка simple ценников используя оригинальный шаблон
     */
    private function createSimplePriceTagsListHtml($priceTags)
    {
        
        // Загружаем оригинальный шаблон
        $templateFile = $this->templatesPath . 'simple/index.html';
        $cssFile = $this->templatesPath . 'simple/styles.css';
        
        if (!file_exists($templateFile)) {
            echo "Шаблон simple не найден\n";
            return '';
        }
        
        $template = file_get_contents($templateFile);
        $css = '';
        if (file_exists($cssFile)) {
            $css = file_get_contents($cssFile);
        }
        
        // Создаем HTML с встроенными стилями
        $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список Simple ценников</title>
    <link href="https://fonts.googleapis.com/css?family=Inter&display=swap" rel="stylesheet">
    <style>' . $css . '</style>
</head>
<body>
    <div class="node-1">';
        
        // Группируем товары по 2 (1 ряд по 2)
        $itemsPerBlock = 2;
        $blocks = array_chunk($priceTags, $itemsPerBlock);
        
        foreach ($blocks as $blockIndex => $blockItems) {
            // Создаем flex-block с 2 ценниками (1 ряд)
            $html .= '<div class="flex-block">';
            
            // Добавляем 2 товара в ряд, используя оригинальный шаблон
            for ($i = 0; $i < count($blockItems); $i++) {
                // Заполняем шаблон данными товара
                $itemHtml = $this->fillSimpleTemplate($template, $blockItems[$i]);
                // Извлекаем только первый блок ценника (div.block) из заполненного шаблона
                if (preg_match('/<div\s+class="block"[^>]*>.*?<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/s', $itemHtml, $matches)) {
                    $html .= $matches[0];
                }
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Создание блока ценника для simple шаблона
     */
    private function createSimplePriceTagBlock($item)
    {
        
        // Сначала создаем базовый HTML с правильными данными
        $html = '<div class="block">
            <div class="frame-4-2">
                <div class="frame-25-3">
                    <img src="images/node-4.svg" class="node-4" alt="Товар" />
                </div>
                <div class="frame-26-5">
                    <div class="frame-33-6">
                        <p class="text-7"><span class="text-rgb-30-30-30">';
        
        // Название товара
        if (isset($item['Название товара']) && !empty($item['Название товара'])) {
            $html .= htmlspecialchars($item['Название товара']);
        } else {
            $html .= 'Название товара';
        }
        
        $html .= '</span></p>
                    </div>
                </div>
            </div>
            <div class="frame-24-8">
                <div class="frame-2-9">
                    <div class="frame-6-10">
                        <div class="frame-7-11">
                            <div class="icons-sbi-12">
                                <img src="images/vector-13.svg" class="vector-13" alt="vector" />
                            </div>
                            <div class="frame-12-14">
                                <p class="text-15"><span class="text-rgb-107-107-107">Камера</span></p>
                                <p class="text-16"><span class="text-rgb-30-30-30">';
        
        // Характеристика камеры
        if (isset($item['Камера ']) && !empty($item['Камера '])) {
            $html .= htmlspecialchars($item['Камера ']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                            </div>
                        </div>
                        <div class="frame-8-17">
                            <div class="icons-sbi-18">
                                <img src="images/vector-19.svg" class="vector-19" alt="vector" />
                            </div>
                            <div class="frame-12-20">
                                <p class="text-21"><span class="text-rgb-107-107-107">Дисплей</span></p>
                                <p class="text-22"><span class="text-rgb-30-30-30">';
        
        // Характеристика дисплея
        if (isset($item['Дисплей']) && !empty($item['Дисплей'])) {
            $html .= htmlspecialchars($item['Дисплей']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                            </div>
                        </div>
                    </div>
                    <div class="frame-5-23">
                        <div class="frame-7-24">
                            <div class="icons-sbi-25">
                                <img src="images/vector-26.svg" class="vector-26" alt="vector" />
                            </div>
                            <div class="frame-12-27">
                                <p class="text-28"><span class="text-rgb-107-107-107">Батарея</span></p>
                                <p class="text-29"><span class="text-rgb-30-30-30">';
        
        // Характеристика батареи
        if (isset($item['Батарея']) && !empty($item['Батарея'])) {
            $html .= htmlspecialchars($item['Батарея']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                            </div>
                        </div>
                        <div class="frame-8-30">
                            <div class="icons-sbi-31">
                                <img src="images/vector-32.svg" class="vector-32" alt="vector" />
                            </div>
                            <div class="frame-12-33">
                                <p class="text-34"><span class="text-rgb-107-107-107">Память</span></p>
                                <p class="text-35"><span class="text-rgb-30-30-30">';
        
        // Характеристика памяти
        if (isset($item['Память']) && !empty($item['Память'])) {
            $html .= htmlspecialchars($item['Память']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="frame-23-36">
                    <div class="frame-20-37">
                        <div class="frame-17-38">
                            <div class="frame-22-39">
                                <p class="text-40"><span class="text-white">Цена в рассрочку:</span></p>
                                <div class="frame-32-41">
                                    <p class="text-42"><span class="text-white">от</span></p>
                                    <p class="text-43"><span class="text-white">';
        
        // Цена в рассрочку - если пустая, показываем прочерк
        if (isset($item['Цена в рассорочку']) && !empty($item['Цена в рассорочку'])) {
            $html .= $this->formatPrice($item['Цена в рассорочку']);
        } else {
            // Если цены в рассрочку нет, показываем прочерк
            $html .= '—';
        }
        
        $html .= '</span></p>
                                    <p class="text-44"><span class="text-white">сум/мес</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="frame-15-45">';
        
        // Старая цена - если есть, показываем
        if (isset($item['Старая Цена']) && !empty($item['Старая Цена'])) {
            $oldPrice = $this->formatPrice($item['Старая Цена']);
            $html .= '<span class="old-price">' . $oldPrice . '</span>';
        }
        
        $html .= '<p class="text-46"><span class="text-white">Цена без рассрочки:</span></p>
                            <p class="text-47"><span class="text-white">';
        
        // Цена без рассрочки - если пустая, показываем прочерк
        if (isset($item['Цена без рассрочки']) && !empty($item['Цена без рассрочки'])) {
            $html .= $this->formatPrice($item['Цена без рассрочки']);
        } else {
            // Если цены без рассрочки нет, показываем прочерк
            $html .= '—';
        }
        
        $html .= '</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        
        return $html;
    }
    
    /**
     * Генерация списка simple_accessories ценников используя оригинальный шаблон
     */
    private function generateSimpleAccessoriesPriceTagsList($filename)
    {
        $filePath = $this->excelPath . $filename;
        
        if (!file_exists($filePath)) {
            echo "Файл {$filename} не найден\n";
            return;
        }
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $this->parseExcelData($worksheet);
        } catch (Exception $e) {
            echo "Ошибка при обработке файла {$filename}: " . $e->getMessage() . "\n";
            return;
        }
        
        if (empty($data)) {
            echo "Нет данных для генерации simple_accessories ценников\n";
            return;
        }
        
        echo "Загружено simple_accessories: " . count($data) . " товаров\n";
        
        // Отладочная информация - показываем доступные поля
        if (!empty($data)) {
            echo "Доступные поля в первом товаре:\n";
            foreach (array_keys($data[0]) as $field) {
                echo "  - " . $field . "\n";
            }
            echo "Первый товар:\n";
            foreach ($data[0] as $field => $value) {
                echo "  " . $field . ": " . $value . "\n";
            }
        }
        
        // Создаем HTML используя оригинальный шаблон
        $html = $this->createSimpleAccessoriesPriceTagsListHtml($data);
        
        // Сохраняем в файл
        $outputFile = $this->resultsPath . 'simple_accessories/simple_accessories_price_tags_list.html';
        file_put_contents($outputFile, $html);
        
        echo "Создан список simple_accessories ценников: {$outputFile}\n";
        echo "Всего товаров: " . count($data) . "\n";
        echo "\n💡 СОВЕТ: Для создания PDF откройте HTML файл в браузере и используйте Печать -> Сохранить как PDF\n";
        echo "   При печати выберите размер страницы A4 и установите масштаб 100%\n";
    }
    

    
    /**
     * Генерация списка accessories ценников используя оригинальный шаблон
     */
        private function generateAccessoriesPriceTagsList($filename)
    {
        $filePath = $this->excelPath . $filename;
        
        if (!file_exists($filePath)) {
            echo "Файл {$filename} не найден\n";
            return;
        }
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $this->parseExcelData($worksheet);
        } catch (Exception $e) {
            echo "Ошибка при обработке файла {$filename}: " . $e->getMessage() . "\n";
            return;
        }
        
        if (empty($data)) {
            echo "Нет данных для генерации accessories ценников\n";
            return;
        }
        
        echo "Загружено accessories: " . count($data) . " товаров\n";
        
        // Отладочная информация - показываем доступные поля
        if (!empty($data)) {
            echo "Доступные поля в первом товаре:\n";
            foreach (array_keys($data[0]) as $field) {
                echo "  - " . $field . "\n";
            }
            echo "Первый товар:\n";
            foreach ($data[0] as $field => $value) {
                echo "  " . $field . ": " . $value . "\n";
            }
        }
        
        // Создаем HTML используя оригинальный шаблон
        $html = $this->createAccessoriesPriceTagsListHtml($data);
        
        // Сохраняем в файл
        $outputFile = $this->resultsPath . 'accessories/accessories_price_tags_list.html';
        file_put_contents($outputFile, $html);
        
        echo "Создан список accessories ценников: {$outputFile}\n";
        echo "Всего товаров: " . count($data) . "\n";
    }
    
    /**
     * Генерация списка promotions ценников используя оригинальный шаблон
     */
    private function generatePromotionsPriceTagsList($filename)
    {
        $filePath = $this->excelPath . $filename;
        
        if (!file_exists($filePath)) {
            echo "Файл {$filename} не найден\n";
            return;
        }
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $this->parseExcelData($worksheet);
            
            if (empty($data)) {
                echo "Нет данных для генерации promotions ценников\n";
                return;
            }
            
            echo "Загружено promotions: " . count($data) . " товаров\n";
            
            // Отладочная информация - показываем доступные поля
            if (!empty($data)) {
                echo "Доступные поля в первом товаре:\n";
                foreach (array_keys($data[0]) as $field) {
                    echo "  - " . $field . "\n";
                }
                echo "Первый товар:\n";
                foreach ($data[0] as $field => $value) {
                    echo "  " . $field . ": " . $value . "\n";
                }
            }
            
            // Создаем HTML используя оригинальный шаблон
            $html = $this->createPromotionsPriceTagsListHtml($data);
            
            // Сохраняем в файл
            $outputFile = $this->resultsPath . 'promotions/promotions_price_tags_list.html';
            file_put_contents($outputFile, $html);
            
            echo "Создан список promotions ценников: {$outputFile}\n";
            echo "Всего товаров: " . count($data) . "\n";
            
        } catch (Exception $e) {
            echo "Ошибка при обработке файла {$filename}: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Создание HTML для списка simple_accessories ценников используя оригинальный шаблон
     */
    private function createSimpleAccessoriesPriceTagsListHtml($priceTags)
    {
        // Загружаем только CSS
        $cssFile = $this->templatesPath . 'simple_accessories/styles.css';
        $css = '';
        if (file_exists($cssFile)) {
            $css = file_get_contents($cssFile);
        }
        
        // Создаем HTML с встроенными стилями
        $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список Simple Accessories ценников</title>
    <link href="https://fonts.googleapis.com/css?family=Inter&display=swap" rel="stylesheet">
    <style>' . $css . '</style>
</head>
<body>
    <div class="node-1">';
        
        // Группируем товары по 1 (1 ценник на страницу при печати)
        $itemsPerBlock = 1;
        $blocks = array_chunk($priceTags, $itemsPerBlock);
        
        foreach ($blocks as $blockIndex => $blockItems) {
            // Создаем flex-block с 1 ценником (1 ценник на страницу)
            $html .= '<div class="flex-block">';
            
            // Добавляем 1 товар в flex-block
            for ($i = 0; $i < count($blockItems); $i++) {
                $html .= $this->createSimpleAccessoriesPriceTagBlock($blockItems[$i]);
        }
        
        $html .= '</div>';
        }
        
        $html .= '</div>
</body>
</html>';
        
        return $html;
    }

    /**
     * Создание HTML для списка accessories ценников используя оригинальный шаблон
     */
    private function createAccessoriesPriceTagsListHtml($priceTags)
    {
        // Загружаем оригинальный шаблон
        $templateFile = $this->templatesPath . 'accessories/index.html';
        $cssFile = $this->templatesPath . 'accessories/styles.css';
        
        if (!file_exists($templateFile)) {
            echo "Шаблон accessories не найден\n";
            return '';
        }
        
        $template = file_get_contents($templateFile);
        $css = '';
        if (file_exists($cssFile)) {
            $css = file_get_contents($cssFile);
        }
        
        // Создаем HTML с встроенными стилями
        $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список Accessories ценников</title>
    <link href="https://fonts.googleapis.com/css?family=Inter&display=swap" rel="stylesheet">
    <style>' . $css . '</style>
</head>
<body>
    <div class="node-1">';
        
        // Группируем товары по 2 (1 ряд)
        $itemsPerBlock = 2;
        $blocks = array_chunk($priceTags, $itemsPerBlock);
        
        foreach ($blocks as $blockIndex => $blockItems) {
            // Создаем flex-block с 2 ценниками (1 ряд)
            $html .= '<div class="flex-block">';
            
            // Добавляем 2 товара в flex-block
            for ($i = 0; $i < count($blockItems); $i++) {
                $html .= $this->createAccessoriesPriceTagBlock($blockItems[$i]);
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Создание HTML для списка promotions ценников используя оригинальный шаблон
     */
    private function createPromotionsPriceTagsListHtml($priceTags)
    {
        // Загружаем оригинальный шаблон
        $templateFile = $this->templatesPath . 'promotions/index.html';
        $cssFile = $this->templatesPath . 'promotions/styles.css';
        
        if (!file_exists($templateFile)) {
            echo "Шаблон promotions не найден\n";
            return '';
        }
        
        $template = file_get_contents($templateFile);
        $css = '';
        if (file_exists($cssFile)) {
            $css = file_get_contents($cssFile);
        }
        
        // Создаем HTML с встроенными стилями
        $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список Promotions ценников</title>
    <link href="https://fonts.googleapis.com/css?family=Inter&display=swap" rel="stylesheet">
    <style>' . $css . '</style>
</head>
<body>
    <div class="node-1 sale">';
        
        // Группируем товары по 2 (1 ряд)
        $itemsPerBlock = 2;
        $blocks = array_chunk($priceTags, $itemsPerBlock);
        
        foreach ($blocks as $blockIndex => $blockItems) {
            // Создаем flex-block с 2 ценниками (1 ряд)
            $html .= '<div class="flex-block">';
            
            // Добавляем 2 товара в flex-block
            for ($i = 0; $i < count($blockItems); $i++) {
                $html .= $this->createPromotionsPriceTagBlock($blockItems[$i]);
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Создание блока ценника для simple_accessories шаблона
     */
    private function createSimpleAccessoriesPriceTagBlock($item)
    {
        // Создаем HTML напрямую, только блок без node-1 и flex-block
        $html = '<div class="block">
            <div class="frame-30-2">
                <p class="text-3"><span class="text-white">';
        
        // Название товара
        if (isset($item['Название']) && !empty($item['Название'])) {
            $html .= htmlspecialchars($item['Название']);
        } else {
            $html .= 'Название товара';
        }
        
        $html .= '</span></p>
            </div>
            <div class="frame-31-4">
                <div class="frame-17-6">
                    <p class="text-7">
                        <span class="text-white">';
        
        // Цена
        if (isset($item['Цена']) && !empty($item['Цена'])) {
            $html .= $this->formatPrice($item['Цена']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                </div>
            </div>
        </div>';
        
        return $html;
    }
    

    


    
    /**
     * Создание блока ценника для accessories шаблона
     */
    private function createAccessoriesPriceTagBlock($item)
    {
        $html = '<div class="block accessories-block">
            <div class="frame-30-2">
                <p class="text-3"><span class="text-white">';
        
        // Название товара
        if (isset($item['Название']) && !empty($item['Название'])) {
            $html .= htmlspecialchars($item['Название']);
        } else {
            $html .= 'Название товара';
        }
        
        $html .= '</span></p>
            </div>
            <div class="frame-31-4">
                <p class="text-5"><span class="text-white">';
        
        // Старая цена (если есть)
        if (isset($item['Старая цена']) && !empty($item['Старая цена'])) {
            $html .= $this->formatPrice($item['Старая цена']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                <div class="frame-17-6">
                    <p class="text-7"><span class="text-white">';
        
        // Текущая цена
        if (isset($item['Цена']) && !empty($item['Цена'])) {
            $html .= $this->formatPrice($item['Цена']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                </div>
                <div class="frame-15-8">
                    <p class="text-9"><span class="text-white">';
        
        // Рассрочка
        if (isset($item['Рассрочка']) && !empty($item['Рассрочка'])) {
            $html .= htmlspecialchars($item['Рассрочка']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                </div>
            </div>
        </div>';
        
        return $html;
    }
    
    /**
     * Создание блока ценника для promotions шаблона
     */
    private function createPromotionsPriceTagBlock($item)
    {
        $html = '<div class="block">
            <div class="frame-4-2">
                <div class="frame-25-3">
                    <img src="images/node-4.svg" class="node-4" alt="Товар" />
                </div>
                <div class="frame-26-5">
                    <div class="frame-33-6">
                        <p class="text-7"><span class="text-rgb-30-30-30">';
        
        // Название товара
        if (isset($item['Название товара']) && !empty($item['Название товара'])) {
            $html .= htmlspecialchars($item['Название товара']);
        } else {
            $html .= 'Название товара';
        }
        
        $html .= '</span></p>
                    </div>
                </div>
            </div>
            <div class="frame-24-8">
                <div class="frame-2-9">
                    <div class="frame-6-10">
                        <div class="frame-7-11">
                            <div class="icons-sbi-12">
                                <img src="images/vector-13.svg" class="vector-13" alt="vector" />
                            </div>
                            <div class="frame-12-14">
                                <p class="text-15"><span class="text-rgb-107-107-107">Камера</span></p>
                                <p class="text-16"><span class="text-rgb-30-30-30">';
        
        // Характеристика камеры
        if (isset($item['Камера ']) && !empty($item['Камера '])) {
            $html .= htmlspecialchars($item['Камера ']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                            </div>
                        </div>
                        <div class="frame-8-17">
                            <div class="icons-sbi-18">
                                <img src="images/vector-19.svg" class="vector-19" alt="vector" />
                            </div>
                            <div class="frame-12-20">
                                <p class="text-21"><span class="text-rgb-107-107-107">Дисплей</span></p>
                                <p class="text-22"><span class="text-rgb-30-30-30">';
        
        // Характеристика дисплея
        if (isset($item['Дисплей']) && !empty($item['Дисплей'])) {
            $html .= htmlspecialchars($item['Дисплей']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                            </div>
                        </div>
                    </div>
                    <div class="frame-5-23">
                        <div class="frame-7-24">
                            <div class="icons-sbi-25">
                                <img src="images/vector-26.svg" class="vector-26" alt="vector" />
                            </div>
                            <div class="frame-12-27">
                                <p class="text-28"><span class="text-rgb-107-107-107">Батарея</span></p>
                                <p class="text-29"><span class="text-rgb-30-30-30">';
        
        // Характеристика батареи
        if (isset($item['Батарея']) && !empty($item['Батарея'])) {
            $html .= htmlspecialchars($item['Батарея']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                            </div>
                        </div>
                        <div class="frame-8-30">
                            <div class="icons-sbi-31">
                                <img src="images/vector-32.svg" class="vector-32" alt="vector" />
                            </div>
                            <div class="frame-12-33">
                                <p class="text-34"><span class="text-rgb-107-107-107">Память</span></p>
                                <p class="text-35"><span class="text-rgb-30-30-30">';
        
        // Характеристика памяти
        if (isset($item['Память']) && !empty($item['Память'])) {
            $html .= htmlspecialchars($item['Память']);
        } else {
            $html .= '—';
        }
        
        $html .= '</span></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="frame-23-36">
                    <div class="frame-20-37">
                        <div class="frame-17-38">
                            <div class="frame-22-39">
                                <p class="text-40"><span class="text-white">Цена в рассрочку:</span></p>
                                <div class="frame-32-41">
                                    <p class="text-42"><span class="text-white">от</span></p>
                                    <p class="text-43"><span class="text-white">';
        
        // Цена в рассрочку - если пустая, показываем прочерк
        if (isset($item['Цена с рассрочкой']) && !empty($item['Цена с рассрочкой'])) {
            $html .= $this->formatPrice($item['Цена с рассрочкой']);
        } else {
            // Если цены в рассрочку нет, показываем прочерк
            $html .= '—';
        }
        
        $html .= '</span></p>
                                    <p class="text-44"><span class="text-white">сум/мес</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="frame-15-45">';
        
        // Старая цена (если есть)
        if (isset($item['Старая Цена']) && !empty($item['Старая Цена'])) {
            $html .= '<span class="old-price">' . $this->formatPrice($item['Старая Цена']) . '</span>';
        }
        
        $html .= '<p class="text-46">
                            <span class="text-white">Цена без рассрочки:</span>
                        </p>
                        <p class="text-47"><span class="text-white">';
        
        // Цена без рассрочки - если пустая, показываем прочерк
        if (isset($item['Цена без рассрочки']) && !empty($item['Цена без рассрочки'])) {
            $html .= $this->formatPrice($item['Цена без рассрочки']);
        } else {
            // Если цены без рассрочки нет, показываем прочерк
            $html .= '—';
        }
        
        $html .= '</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        
        return $html;
    }
}

// Запуск генератора
if (php_sapi_name() === 'cli') {
    $generator = new PriceTagGenerator();
    
    // Режим генерации: 'print' - только лист для печати, 'individual' - отдельные ценники
    $mode = isset($argv[1]) ? $argv[1] : 'print';
    
    if ($mode === 'print') {
        echo "Режим: Генерация листа для печати\n";
        $generator->generateAll('print');
    } elseif ($mode === 'individual') {
        echo "Режим: Генерация отдельных ценников\n";
        $generator->generateAll('individual');
    } elseif ($mode === 'list') {
        echo "Режим: Генерация списка ценников\n";
        $generator->generateAll('list');
    } elseif ($mode === 'simple-list') {
        echo "Режим: Генерация списка simple ценников используя шаблон\n";
        $generator->generateAll('simple-list');
    } elseif ($mode === 'promotions-list') {
        echo "Режим: Генерация списка promotions ценников используя шаблон\n";
        $generator->generateAll('promotions-list');
    } elseif ($mode === 'accessories-list') {
        echo "Режим: Генерация списка accessories ценников используя шаблон\n";
        $generator->generateAll('accessories-list');
    } elseif ($mode === 'simple-accessories-list') {
        echo "Режим: Генерация списка simple_accessories ценников используя шаблон\n";
        $generator->generateAll('simple-accessories-list');
    } else {
        echo "Использование:\n";
        echo "  php price_generator.php          - генерация листа для печати (по умолчанию)\n";
        echo "  php price_generator.php print    - генерация листа для печати\n";
        echo "  php price_generator.php individual - генерация отдельных ценников\n";
        echo "  php price_generator.php list     - генерация списка ценников\n";
        echo "  php price_generator.php simple-list - генерация списка simple ценников используя шаблон\n";
        echo "  php price_generator.php promotions-list - генерация списка promotions ценников используя шаблон\n";
        echo "  php price_generator.php accessories-list - генерация списка accessories ценников используя шаблон\n";
        echo "  php price_generator.php simple-accessories-list - генерация списка simple_accessories ценников используя шаблон\n";
        exit(1);
    }
} else {
    echo "Этот скрипт предназначен для запуска из командной строки.\n";
}
?>
