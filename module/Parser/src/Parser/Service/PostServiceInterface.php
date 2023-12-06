<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 07.03.2017
 * Time: 4:19
 */

namespace Parser\Service;

use Parser\Model\PostInterface;

interface PostServiceInterface
{
    /**
     * Should return a set of all parser posts that we can iterate over. Single entries of the array are supposed to be
     * implementing \Parser\Model\PostInterface
     *
     * @return array|PostInterface[]
     */
    public function findAllPosts();

    /**
     * Should return a single parser post
     *
     * @param  int $id Identifier of the Post that should be returned
     * @return PostInterface
     */
    public function findPost($id);
}