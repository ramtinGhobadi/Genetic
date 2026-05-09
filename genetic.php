<?php
/**
 * کلاس الگوریتم ژنتیک
 * Genetic Algorithm Implementation in PHP
 */
class GeneticAlgorithm
{
    private $populationSize;     // اندازه جمعیت
    private $chromosomeLength;   // طول هر کروموزوم (تعداد ژن‌ها)
    private $mutationRate;       // نرخ جهش (0 تا 1)
    private $crossoverRate;      // نرخ ترکیب (0 تا 1)
    private $generations;        // تعداد نسل‌ها
    private $population;         // جمعیت فعلی
    private $fitnessCache;       // کش مقادیر fitness
    private $elitismCount;       // تعداد بهترین کروموزوم‌هایی که مستقیماً به نسل بعد میروند
    
    /**
     * سازنده کلاس
     */
    public function __construct($populationSize = 100, $chromosomeLength = 20, $mutationRate = 0.01, $crossoverRate = 0.8, $generations = 100, $elitismCount = 2)
    {
        $this->populationSize = $populationSize;
        $this->chromosomeLength = $chromosomeLength;
        $this->mutationRate = $mutationRate;
        $this->crossoverRate = $crossoverRate;
        $this->generations = $generations;
        $this->elitismCount = $elitismCount;
        $this->population = [];
        $this->fitnessCache = [];
    }
    
    /**
     * تولید کروموزوم تصادفی (به صورت باینری)
     */
    public function randomChromosome()
    {
        $chromosome = '';
        for ($i = 0; $i < $this->chromosomeLength; $i++) {
            $chromosome .= mt_rand(0, 1);
        }
        return $chromosome;
    }
    
    /**
     * مقداردهی اولیه جمعیت
     */
    public function initializePopulation()
    {
        $this->population = [];
        for ($i = 0; $i < $this->populationSize; $i++) {
            $this->population[] = $this->randomChromosome();
        }
    }
    
    /**
     * تبدیل کروموزوم باینری به عدد اعشاری در بازه [min, max]
     */
    public function decode($chromosome, $min = -10, $max = 10)
    {
        $binaryValue = bindec($chromosome);
        $maxBinary = pow(2, $this->chromosomeLength) - 1;
        
        // نگاشت از بازه [0, maxBinary] به [min, max]
        return $min + ($binaryValue / $maxBinary) * ($max - $min);
    }
    
    /**
     * تابع هدف (Fitness Function)
     * مثال: f(x) = x * sin(10*pi*x) + 1  در بازه [-2, 2]
     * این تابع ماکزیمم در حدود x = 1.85 دارد
     */
    public function fitnessFunction($chromosome)
    {
        $x = $this->decode($chromosome, -2, 2);
        
        // تابع: f(x) = x * sin(10 * pi * x) + 1
        $fitness = $x * sin(10 * M_PI * $x) + 1;
        
        // اطمینان از مثبت بودن fitness (برای انتخاب بهتر)
        return max($fitness, 0);
    }
    
    /**
     * محاسبه fitness برای یک کروموزوم (با کش)
     */
    public function calculateFitness($chromosome)
    {
        if (!isset($this->fitnessCache[$chromosome])) {
            $this->fitnessCache[$chromosome] = $this->fitnessFunction($chromosome);
        }
        return $this->fitnessCache[$chromosome];
    }
    
    /**
     * انتخاب والدین با روش رولت (Fitness Proportionate Selection)
     */
    public function selectParent()
    {
        // محاسبه مجموع fitness کل جمعیت
        $totalFitness = 0;
        $fitnessValues = [];
        foreach ($this->population as $chromosome) {
            $fitness = $this->calculateFitness($chromosome);
            $fitnessValues[] = $fitness;
            $totalFitness += $fitness;
        }
        
        if ($totalFitness == 0) {
            // اگر همه fitness صفر هستند، تصادفی انتخاب کن
            return $this->population[array_rand($this->population)];
        }
        
        // انتخاب تصادفی بر اساس احتمال
        $random = mt_rand() / mt_getrandmax() * $totalFitness;
        $sum = 0;
        foreach ($this->population as $index => $chromosome) {
            $sum += $fitnessValues[$index];
            if ($sum >= $random) {
                return $chromosome;
            }
        }
        
        return $this->population[0];
    }
    
    /**
     * عملگر ترکیب (Crossover) - یک نقطه برش
     */
    public function crossover($parent1, $parent2)
    {
        // بررسی احتمال انجام ترکیب
        if (mt_rand() / mt_getrandmax() > $this->crossoverRate) {
            return [$parent1, $parent2];
        }
        
        // انتخاب نقطه برش تصادفی
        $crossoverPoint = mt_rand(1, $this->chromosomeLength - 1);
        
        // تولید فرزندان
        $child1 = substr($parent1, 0, $crossoverPoint) . substr($parent2, $crossoverPoint);
        $child2 = substr($parent2, 0, $crossoverPoint) . substr($parent1, $crossoverPoint);
        
        return [$child1, $child2];
    }
    
    /**
     * عملگر جهش (Mutation)
     */
    public function mutate($chromosome)
    {
        $chromosomeArray = str_split($chromosome);
        
        for ($i = 0; $i < $this->chromosomeLength; $i++) {
            if (mt_rand() / mt_getrandmax() < $this->mutationRate) {
                // معکوس کردن بیت
                $chromosomeArray[$i] = $chromosomeArray[$i] == '0' ? '1' : '0';
            }
        }
        
        return implode('', $chromosomeArray);
    }
    
    /**
     * اجرای یک نسل (تولید نسل جدید)
     */
    public function evolve()
    {
        $newPopulation = [];
        
        // Elitism: حفظ بهترین کروموزوم‌ها
        if ($this->elitismCount > 0) {
            // مرتب‌سازی جمعیت بر اساس fitness (نزولی)
            usort($this->population, function($a, $b) {
                return $this->calculateFitness($b) <=> $this->calculateFitness($a);
            });
            
            // اضافه کردن بهترین کروموزوم‌ها به نسل جدید
            for ($i = 0; $i < $this->elitismCount; $i++) {
                $newPopulation[] = $this->population[$i];
            }
        }
        
        // تولید بقیه جمعیت
        while (count($newPopulation) < $this->populationSize) {
            // انتخاب والدین
            $parent1 = $this->selectParent();
            $parent2 = $this->selectParent();
            
            // ترکیب
            list($child1, $child2) = $this->crossover($parent1, $parent2);
            
            // جهش
            $child1 = $this->mutate($child1);
            $child2 = $this->mutate($child2);
            
            $newPopulation[] = $child1;
            if (count($newPopulation) < $this->populationSize) {
                $newPopulation[] = $child2;
            }
        }
        
        $this->population = $newPopulation;
        // پاک کردن کش fitness برای نسل جدید
        $this->fitnessCache = [];
    }
    
    /**
     * پیدا کردن بهترین کروموزوم در جمعیت فعلی
     */
    public function getBestChromosome()
    {
        $bestChromosome = null;
        $bestFitness = -INF;
        
        foreach ($this->population as $chromosome) {
            $fitness = $this->calculateFitness($chromosome);
            if ($fitness > $bestFitness) {
                $bestFitness = $fitness;
                $bestChromosome = $chromosome;
            }
        }
        
        return [
            'chromosome' => $bestChromosome,
            'fitness' => $bestFitness,
            'x' => $this->decode($bestChromosome, -2, 2)
        ];
    }
    
    /**
     * اجرای کامل الگوریتم ژنتیک
     */
    public function run($verbose = true)
    {
        $this->initializePopulation();
        $bestHistory = [];
        
        for ($gen = 1; $gen <= $this->generations; $gen++) {
            $this->evolve();
            $best = $this->getBestChromosome();
            $bestHistory[] = $best;
            
            if ($verbose && $gen % 10 == 0) {
                echo "نسل {$gen}: بهترین مقدار = " . round($best['fitness'], 6) . 
                     " (x = " . round($best['x'], 6) . ")\n";
            }
        }
        
        return [
            'best' => $this->getBestChromosome(),
            'history' => $bestHistory
        ];
    }
}

// ============================================
// مثال 1: بهینه‌سازی تابع ساده
// ============================================
echo "========== مثال 1: پیدا کردن ماکزیمم تابع f(x) = x * sin(10*pi*x) + 1 ==========\n\n";

$ga = new GeneticAlgorithm(
    populationSize: 100,     // 100 کروموزوم
    chromosomeLength: 20,    // 20 بیت (دقت حدود 0.000004)
    mutationRate: 0.01,      // 1% نرخ جهش
    crossoverRate: 0.8,      // 80% نرخ ترکیب
    generations: 100,        // 100 نسل
    elitismCount: 2          // حفظ 2 تا از بهترین‌ها
);

$result = $ga->run(true);

echo "\n========== نتیجه نهایی ==========\n";
echo "بهترین کروموزوم: " . $result['best']['chromosome'] . "\n";
echo "مقدار x بهینه: " . round($result['best']['x'], 6) . "\n";
echo "حداکثر مقدار تابع: " . round($result['best']['fitness'], 6) . "\n";

// ============================================
// مثال 2: پیدا کردن ماکزیمم تابع درجه 2 (ساده‌تر)
// ============================================
echo "\n\n========== مثال 2: پیدا کردن ماکزیمم f(x) = -x² + 5x + 3 در بازه [0, 5] ==========\n\n";

class SimpleGA extends GeneticAlgorithm
{
    public function fitnessFunction($chromosome)
    {
        $x = $this->decode($chromosome, 0, 5);
        // f(x) = -x² + 5x + 3
        $fitness = -pow($x, 2) + 5 * $x + 3;
        return max($fitness, 0);
    }
}

$simpleGa = new SimpleGA(
    populationSize: 50,
    chromosomeLength: 16,  // دقت کمتر برای سرعت بیشتر
    mutationRate: 0.02,
    crossoverRate: 0.85,
    generations: 50,
    elitismCount: 1
);

$result2 = $simpleGa->run(true);
echo "\n========== نتیجه نهایی ==========\n";
echo "مقدار x بهینه: " . round($result2['best']['x'], 6) . "\n";
echo "حداکثر مقدار تابع: " . round($result2['best']['fitness'], 6) . "\n";
echo "مقدار واقعی ماکزیمم در x=2.5: " . ( -pow(2.5, 2) + 5*2.5 + 3 ) . "\n";

// ============================================
// مثال 3: حل مسئله مسیریابی فروشنده (TSP) ساده
// ============================================
echo "\n\n========== مثال 3: مسیریابی فروشنده (6 شهر) ==========\n\n";

class TSPGeneticAlgorithm extends GeneticAlgorithm
{
    private $cities = [];
    private $distanceMatrix = [];
    
    public function __construct($cities, $populationSize = 100, $mutationRate = 0.05, $crossoverRate = 0.8, $generations = 200, $elitismCount = 2)
    {
        $this->cities = $cities;
        $numCities = count($cities);
        parent::__construct($populationSize, $numCities, $mutationRate, $crossoverRate, $generations, $elitismCount);
        
        // محاسبه ماتریس فواصل
        for ($i = 0; $i < $numCities; $i++) {
            for ($j = 0; $j < $numCities; $j++) {
                $dx = $cities[$i]['x'] - $cities[$j]['x'];
                $dy = $cities[$i]['y'] - $cities[$j]['y'];
                $this->distanceMatrix[$i][$j] = sqrt($dx * $dx + $dy * $dy);
            }
        }
    }
    
    // برای TSP، کروموزوم یک جایگشت از شهرهاست، نه باینری
    public function randomChromosome()
    {
        $chromosome = range(0, $this->chromosomeLength - 1);
        shuffle($chromosome);
        return implode(',', $chromosome);
    }
    
    public function decode($chromosome, $min = null, $max = null)
    {
        return explode(',', $chromosome);
    }
    
    public function fitnessFunction($chromosome)
    {
        $path = explode(',', $chromosome);
        $totalDistance = 0;
        
        for ($i = 0; $i < count($path) - 1; $i++) {
            $totalDistance += $this->distanceMatrix[$path[$i]][$path[$i + 1]];
        }
        // بازگشت به شهر اول
        $totalDistance += $this->distanceMatrix[$path[count($path) - 1]][$path[0]];
        
        // Fitness = 1 / distance (هر چه فاصله کمتر، fitness بیشتر)
        return 1 / ($totalDistance + 0.0001);
    }
    
    public function crossover($parent1, $parent2)
    {
        if (mt_rand() / mt_getrandmax() > $this->crossoverRate) {
            return [$parent1, $parent2];
        }
        
        $p1 = explode(',', $parent1);
        $p2 = explode(',', $parent2);
        $size = count($p1);
        
        // روش ترکیب PMX (Partially Mapped Crossover)
        $start = mt_rand(0, $size - 2);
        $end = mt_rand($start + 1, $size - 1);
        
        $child1 = array_fill(0, $size, null);
        $child2 = array_fill(0, $size, null);
        
        // کپی بخش انتخابی
        for ($i = $start; $i <= $end; $i++) {
            $child1[$i] = $p1[$i];
            $child2[$i] = $p2[$i];
        }
        
        // تکمیل فرزند اول
        for ($i = 0; $i < $size; $i++) {
            if ($child1[$i] === null) {
                $value = $p2[$i];
                while (in_array($value, $child1)) {
                    $pos = array_search($value, $p2);
                    $value = $p1[$pos];
                }
                $child1[$i] = $value;
            }
        }
        
        // تکمیل فرزند دوم
        for ($i = 0; $i < $size; $i++) {
            if ($child2[$i] === null) {
                $value = $p1[$i];
                while (in_array($value, $child2)) {
                    $pos = array_search($value, $p1);
                    $value = $p2[$pos];
                }
                $child2[$i] = $value;
            }
        }
        
        return [implode(',', $child1), implode(',', $child2)];
    }
    
    public function mutate($chromosome)
    {
        if (mt_rand() / mt_getrandmax() > $this->mutationRate) {
            return $chromosome;
        }
        
        $path = explode(',', $chromosome);
        $i = mt_rand(0, count($path) - 1);
        $j = mt_rand(0, count($path) - 1);
        
        // swap mutation
        $temp = $path[$i];
        $path[$i] = $path[$j];
        $path[$j] = $temp;
        
        return implode(',', $path);
    }
    
    public function calculateFitness($chromosome)
    {
        if (!isset($this->fitnessCache[$chromosome])) {
            $this->fitnessCache[$chromosome] = $this->fitnessFunction($chromosome);
        }
        return $this->fitnessCache[$chromosome];
    }
    
    public function getBestChromosome()
    {
        $bestChromosome = null;
        $bestFitness = -INF;
        
        foreach ($this->population as $chromosome) {
            $fitness = $this->calculateFitness($chromosome);
            if ($fitness > $bestFitness) {
                $bestFitness = $fitness;
                $bestChromosome = $chromosome;
            }
        }
        
        $path = explode(',', $bestChromosome);
        $totalDistance = 0;
        for ($i = 0; $i < count($path) - 1; $i++) {
            $totalDistance += $this->distanceMatrix[$path[$i]][$path[$i + 1]];
        }
        $totalDistance += $this->distanceMatrix[$path[count($path) - 1]][$path[0]];
        
        return [
            'chromosome' => $bestChromosome,
            'fitness' => $bestFitness,
            'path' => $path,
            'distance' => $totalDistance
        ];
    }
}

// شهرهای نمونه (مختصات)
$cities = [
    ['name' => 'تهران', 'x' => 0, 'y' => 0],
    ['name' => 'اصفهان', 'x' => 10, 'y' => 8],
    ['name' => 'شیراز', 'x' => 15, 'y' => 20],
    ['name' => 'مشهد', 'x' => 25, 'y' => 5],
    ['name' => 'تبریز', 'x' => 5, 'y' => 15],
    ['name' => 'کرمان', 'x' => 20, 'y' => 18]
];

$tspGa = new TSPGeneticAlgorithm($cities, 80, 0.08, 0.7, 150, 2);
$tspResult = $tspGa->run(true);

echo "\n========== بهترین مسیر پیدا شده ==========\n";
echo "مسیر: ";
foreach ($tspResult['best']['path'] as $cityIndex) {
    echo $cities[$cityIndex]['name'] . " → ";
}
echo $cities[$tspResult['best']['path'][0]]['name'] . "\n";
echo "طول کل مسیر: " . round($tspResult['best']['distance'], 2) . " واحد\n";

?>