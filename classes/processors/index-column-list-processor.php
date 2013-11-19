<?php
/**
 * index-column-list-processor.php
 *
 * This file implements the processor for index column lists.
 *
 * Copyright (c) 2010-2012, Justin Swanhart
 * with contributions by André Rothe <arothe@phosco.info, phosco@gmx.de>
 *
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *   * Redistributions of source code must retain the above copyright notice,
 *     this list of conditions and the following disclaimer.
 *   * Redistributions in binary form must reproduce the above copyright notice,
 *     this list of conditions and the following disclaimer in the documentation
 *     and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT
 * SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
 * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR
 * BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 */
if (!defined('HAVE_IDX_COL_LIST_PROCESSOR')) {
    require_once(dirname(__FILE__) . '/abstract-processor.php');
    require_once(dirname(__FILE__) . '/../expression-types.php');

    /**
     * 
     * This class processes the index column lists.
     * 
     * @author arothe
     * 
     */
    class IndexColumnListProcessor extends AbstractProcessor {

        public function process($sql) {
            $tokens = $this->splitSQLIntoTokens($sql);

            $expr = array();
            $result = array();
            $base_expr = "";

            foreach ($tokens as $k => $token) {

                $trim = trim($token);
                $base_expr .= $token;

                if ($trim === "") {
                    continue;
                }

                $upper = strtoupper($trim);

                switch ($upper) {

                case 'ASC':
                case 'DESC':
                # the optional order
                    $expr[] = array('type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                    break;

                case ',':
                # the next column
                    $result[] = array('type' => ExpressionType::INDEX_COLUMN, 'base_expr' => substr(base_expr, 0, -1),
                                      'sub_tree' => $expr);
                    $expr = array();
                    $base_expr = "";
                    break;

                default:
                    if ($upper[0] === '(' && substr($upper, -1) === ')') {
                        # the optional length
                        $length = array('type' => ExpressionType::CONSTANT,
                                        'base_expr' => $this->removeParenthesisFromStart($trim));
                        $expr[] = array('type' => ExpressionType::BRACKET_EXPRESSION, 'base_expr' => $trim,
                                        'sub-tree' => $length);
                        continue 2;
                    }
                    # the col name
                    $expr[] = array('type' => ExpressionType::COLREF, 'base_expr' => $trim,
                                    'no_quotes' => $this->revokeQuotation($trim));
                    break;
                }
            }
            $result[] = array('type' => ExpressionType::INDEX_COLUMN, 'base_expr' => substr(base_expr, 0, -1),
                              'sub_tree' => $expr);
            return $result;
        }
    }
    define('HAVE_IDX_COL_LIST_PROCESSOR', 1);
}
