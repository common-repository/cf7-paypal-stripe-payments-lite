<?php

namespace Cf7Payments;

class Payments
{
    const COLUMNS = [
        'id' => 32,
        'amount' => null,
        'currency' => 4,
        'payer' => 300,
        'claimed' => null,
        'form_id' => null,
        'processor' => 20,
        'date' => null,
    ];

    public static function setupDb()
    {
        global $wpdb;

        $table = $wpdb->prefix . App::PAYMENTS_TABLE;

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        dbDelta("CREATE TABLE IF NOT EXISTS {$table} (
          `id` varchar(32) not null,
          `amount` decimal(10,2) unsigned not null,
          `currency` varchar(4) not null default 'USD',
          `payer` varchar(300),
          `claimed` int unsigned default 0,
          `form_id` int unsigned,
          `processor` varchar(20) not null,
          `date` bigint(20) unsigned not null,
          unique(`id`)
        ) {$wpdb->get_charset_collate()};");
    }

    public static function prepareData( array $args ) : array
    {
        $data = [];
        array_key_exists($k='id', $args) && ($data[$k] = substr(esc_attr($args[$k]), 0, self::COLUMNS[$k]));
        array_key_exists($k='amount', $args) && ($data[$k] = floatval($args[$k] ?? ''));
        array_key_exists($k='currency', $args) && ($data[$k] = substr(esc_attr($args[$k]), 0, self::COLUMNS[$k]));
        array_key_exists($k='payer', $args) && ($data[$k] = substr(esc_attr($args[$k]), 0, self::COLUMNS[$k]));
        array_key_exists($k='claimed', $args) && ($data[$k] = intval($args[$k] ?? ''));
        array_key_exists($k='form_id', $args) && ($data[$k] = intval($args[$k] ?? ''));
        array_key_exists($k='processor', $args) && ($data[$k] = substr(esc_attr($args[$k]), 0, self::COLUMNS[$k]));
        array_key_exists($k='date', $args) && ($data[$k] = (int) $args[$k]);
        return $data;
    }

    public static function insert( array $args ) : bool
    {
        global $wpdb;

        if ( ($args['id'] ?? '') && self::queryOne(['id' => $args['id']]) )
            return false;

        $args['id'] = ($args['id'] ?? '') ?: self::generateId();
        return !! $wpdb->insert( $wpdb->prefix . App::PAYMENTS_TABLE, self::prepareData( $args ) );
    }

    public static function update( string $id, array $args ) : bool
    {
        global $wpdb;
        $data = self::prepareData( $args );
        unset($data['id']);
        return !! $wpdb->update( $wpdb->prefix . App::PAYMENTS_TABLE, $data, [ 'id' => $id ] );
    }

    public static function delete( array $ids ) : int
    {
        global $wpdb;
        $table = $wpdb->prefix . App::PAYMENTS_TABLE;
        return $wpdb->query("delete from {$table} where `id` in (" . join(',', array_map(function($id)
        {
            return '\'' . preg_replace('/[^a-zA-Z0-9-_]/s', '', $id) . '\'';
        }, $ids)) . ")");
    }

    public static function query( array $args=[] ) : array
    {
        global $wpdb;
        $table = $wpdb->prefix . App::PAYMENTS_TABLE;
        $sql = "select o.*, p.post_title from {$table} o";
        $where = ' where 1=1';
        $exec = [];

        foreach ( ['id', 'amount', 'currency', 'payer', 'claimed', 'form_id', 'processor'] as $prop ) {
            if ( isset($args[$prop]) ) {
                $where .= $wpdb->prepare(" and o.{$prop} = %s", $args[$prop]);
            }

            if ( $args["{$prop}_in"] ?? '' ) {
                $where .= $wpdb->prepare(
                    " and o.{$prop} in (" . join( ',', array_fill(0, count((array) $args["{$prop}_in"]), '%s') ) . ')',
                    ...((array) $args["{$prop}_in"])
                );
            }

            if ( $args["{$prop}_not_in"] ?? '' ) {
                $where .= $wpdb->prepare(
                    " and o.{$prop} not in (" . join( ',', array_fill(0, count((array) $args["{$prop}_not_in"]), '%s') ) . ')',
                    ...((array) $args["{$prop}_not_in"])
                );
            }
        }

        if ( $args['search'] ?? '' ) {
            $where .= ' and (
                o.id like \'%' . $wpdb->esc_like($args['search']) . '%\'
                or o.payer like \'%' . $wpdb->esc_like($args['search']) . '%\'
                or o.processor like \'%' . $wpdb->esc_like($args['search']) . '%\'
                or p.post_title like \'%' . $wpdb->esc_like($args['search']) . '%\'
            )';
        }

        $sql .= " left join {$wpdb->posts} p on p.ID = o.form_id";
        $sql .= $where;

        $orderby = sanitize_text_field($args['orderby'] ?? '');
        $orderby = in_array($orderby, array_merge(array_keys(self::COLUMNS), ['rand()', 'post_title'])) ? $orderby : 'date';

        $sql .= " order by {$orderby} ";
        $sql .= in_array(strtolower($args['order'] ?? ''), ['asc', 'desc']) ? strtolower($args['order'] ?? '') : 'desc';

        if ( is_numeric($args['limit'] ?? '') ) {
            $sql .= ' limit ' . intval($args['limit']);
        }

        if ( intval($args['per_page'] ?? 0) <= 0 ) {
            $args['per_page'] = max(1, (int) get_option('cf7-payments_payments_per_page', 20));
        } else {
            $args['per_page'] = (int) $args['per_page'];
        }

        if ( intval( $args['current_page'] ?? 0 ) < 1 ) {
            $args['current_page'] = 1;
        } else {
            $args['current_page'] = (int) $args['current_page'];
        }

        $start = 0;
        for ( $i=2; $i<= $args['current_page']; $i++ ) {
            $start += $args['per_page'];
        }

        $args['per_page']++;
        $sql .= " limit {$start}, {$args['per_page']}";

        $list = [];
        $has_prev = $has_next = null;
        $list = array_map([self::class, 'parseDbItems'], $wpdb->get_results( $sql ));
        $has_prev = ($current_page=$args['current_page']) > 1;
        $has_next = count( $list ) > --$args['per_page'];
        $list = array_slice($list, 0, $args['per_page']);

        return compact('has_prev', 'has_next', 'list', 'current_page');
    }

    public static function queryOne( array $args=[] ) #: ?array
    {
        return self::query( array_merge($args, [ 'per_page' => 1, 'current_page' => 1 ]) )['list'][0] ?? null;
    }

    public static function parseDbItems( $data ) : array
    {
        if ( ! is_array($data) )
            $data = (array) $data;

        $data['id'] = trim( esc_attr( $data['id'] ?: '' ) );
        $data['amount'] = (float) ($data['amount'] ?: null);
        $data['currency'] = trim( esc_attr( $data['currency'] ?: '' ) );
        $data['payer'] = trim( esc_attr( $data['payer'] ?: '' ) );
        $data['claimed'] = (int) ($data['claimed'] ?: null);
        $data['form_id'] = (int) ($data['form_id'] ?: null);
        $data['processor'] = trim( esc_attr( $data['processor'] ?: '' ) );
        $data['date'] = (int) ($data['date'] ?: null);

        return $data;
    }

    public static function generateId() : string
    {
        $hash = bin2hex(random_bytes(16));

        if ( strlen($hash) < 32 ) {
            $hash = bin2hex(random_bytes(32));
        }

        return substr($hash, 0, 32);
    }
}