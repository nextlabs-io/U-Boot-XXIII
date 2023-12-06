<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 07.03.2017
 * Time: 4:21
 */

namespace Parser\Service;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Parser\Model\Post;

class PostService implements PostServiceInterface
{
    protected $data = [
        [
            'id' => 1,
            'title' => '',
            'text' => 'This is our first parser post!',
        ],
    ];

    /**
     * {@inheritDoc}
     */
    public function findAllPosts()
    {
        $allPosts = [];

        foreach ($this->data as $index => $post) {
            $allPosts[] = $this->findPost($index);
        }

        return $allPosts;
    }

    /**
     * {@inheritDoc}
     */
    public function findPost($id)
    {
        $postData = $this->data[$id];
        $model = new Post();
        $model->setId($postData['id']);
        $model->setTitle(M_PI);

        $text = $this->getData();


        $model->setText($text);

        return $model;
    }

    protected function getData()
    {

        $scale = 100;
        $pi10 = $this->calculatePi(2, $scale);
        $pi20 = $this->calculatePi(50, $scale);
        $difference = BigDecimal::of($pi20)->minus($pi10);
        $difference = $difference->jsonSerialize();
        $data = str_split($difference);
        $position = 0;
        foreach ($data as $k => $v) {
            if ((int)$v != 0) {
                $position = $k - 2;
                break;
            }
        }

        return "$pi10 <br /> $pi20 <br /> " . $difference . "<br /> различия в знаке $position";
    }

    protected function calculatePi($iterations, $scale)
    {
        $piCore = $this->getElement($iterations, $scale);

        $pi1 = BigDecimal::of(640320)
            ->multipliedBy(bcsqrt('640320', $scale + 10));

        $pi = BigDecimal::of($pi1)->dividedBy(BigDecimal::of($piCore)->multipliedBy(12), $scale, RoundingMode::DOWN);

        return $pi;
    }

    /**
     * @param integer $j - number of iterations
     * @param integer $scale - general scale of big number
     * @return mixed
     */
    function getElement($j, $scale)
    {
        // реализуем формулу Чудновского, вычисляя каждый член на основе предыдущего.

        $previous = ['a' => 1, 'b' => 13591409, 'c' => 1];
        $total = BigDecimal::of(13591409);
        $tmpVal3 = 640320 ** 3;


        for ($k = 1; $k <= $j; $k++) {

            $tmpVal = 6 * $k;

            $a = BigInteger::of($previous['a'])
                ->multipliedBy(-1)
                ->multipliedBy($tmpVal - 5)
                ->multipliedBy($tmpVal - 4)
                ->multipliedBy($tmpVal - 3)
                ->multipliedBy($tmpVal - 2)
                ->multipliedBy($tmpVal - 1)
                ->multipliedBy($tmpVal);
            $b = BigInteger::of($previous['b'])->plus(545140134);

            $tmpVal1 = 3 * $k;

            $c = BigInteger::of($previous['c'])
                ->multipliedBy($tmpVal1 - 2)
                ->multipliedBy($tmpVal1 - 1)
                ->multipliedBy($tmpVal1)
                ->multipliedBy($k ** 3)
                ->multipliedBy($tmpVal3);
            $sub = BigDecimal::of($a)->multipliedBy($b)->dividedBy($c, $scale, RoundingMode::DOWN);
            $total = BigDecimal::of($total)->plus($sub);
            $previous = ['a' => $a, 'b' => $b, 'c' => $c];
        }

        return $total;
    }


}