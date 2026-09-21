<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Master Data SFA</title>
    <style>
        body {
            margin: 0;
            background: #f4f7fc;
            color: #102047;
            font: 14px Arial, sans-serif
        }

        header {
            padding: 28px max(20px, calc((100% - 1280px)/2));
            background: linear-gradient(135deg, #0e4fbd, #3682ff);
            color: #fff
        }

        h1,
        h2 {
            margin: 0
        }

        main {
            width: min(1280px, calc(100% - 30px));
            margin: 22px auto
        }

        .card {
            background: #fff;
            border: 1px solid #e2e9f5;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 6px 20px #172b4d10
        }

        .forms {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(310px, 1fr));
            gap: 14px
        }

        .head {
            padding: 15px 17px;
            border-bottom: 1px solid #e2e9f5
        }

        .head p,
        small {
            color: #65718a;
            font-size: 12px
        }

        .head p {
            margin: 5px 0
        }

        details summary {
            cursor: pointer;
            list-style: none
        }

        details summary:after {
            content: '+';
            float: right;
            color: #1768f2
        }

        form {
            padding: 16px
        }

        .field {
            display: grid;
            gap: 5px;
            margin-bottom: 10px
        }

        .two {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px
        }

        label {
            font-size: 12px;
            font-weight: 700
        }

        input,
        select,
        textarea {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d7e0ef;
            border-radius: 8px;
            padding: 9px;
            font: inherit
        }

        textarea {
            min-height: 66px
        }

        button {
            background: #1768f2;
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 10px 13px;
            font-weight: bold
        }

        .danger {
            background: #fff0f2;
            color: #aa293a
        }

        .secondary {
            background: #eaf1ff;
            color: #1354c5
        }

        .alert {
            padding: 11px 13px;
            margin-bottom: 14px;
            border-radius: 8px
        }

        .success {
            background: #eafaf2;
            color: #087443
        }

        .errors {
            background: #fff0f2;
            color: #a72b39
        }

        .tables {
            display: grid;
            gap: 15px;
            margin-top: 16px
        }

        .table-wrap {
            overflow: auto
        }

        table {
            width: 100%;
            min-width: 720px;
            border-collapse: collapse
        }

        th,
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e9f5;
            text-align: left;
            vertical-align: top;
            font-size: 12px
        }

        th {
            background: #f9fbff;
            color: #65718a
        }

        .badge {
            padding: 4px 7px;
            border-radius: 99px;
            font-size: 10px;
            font-weight: bold
        }

        .on {
            background: #e9f9f0;
            color: #087a49
        }

        .off {
            background: #fff4dc;
            color: #985d00
        }

        .inline {
            display: inline
        }

        @media(max-width:600px) {
            .two {
                grid-template-columns: 1fr
            }
        }
    </style>
</head>

<body>
    <style>
        body {
            display: flex;
            min-height: 100vh
        }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 245px;
            padding: 24px 16px;
            background: linear-gradient(180deg, #0e4fbd, #1768f2);
            color: #fff;
            z-index: 3
        }

        .brand {
            font-size: 19px;
            font-weight: bold;
            margin-bottom: 5px
        }

        .sub {
            font-size: 11px;
            opacity: .78;
            line-height: 1.5;
            margin-bottom: 28px
        }

        .menu-link {
            display: flex;
            color: #eaf1ff;
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 9px;
            margin-bottom: 5px;
            font-size: 13px;
            font-weight: bold
        }

        .menu-link:hover {
            background: #ffffff24
        }

        .sidebar-footer {
            position: absolute;
            left: 16px;
            right: 16px;
            bottom: 20px;
            font-size: 11px;
            color: #d8e7ff
        }

        .shell {
            margin-left: 245px;
            width: calc(100% - 245px);
            min-height: 100vh
        }

        .topbar {
            min-height: 76px;
            background: #fff;
            border-bottom: 1px solid #e2e9f5;
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 2
        }

        .topbar-title {
            min-width: 175px;
            font-weight: bold
        }

        .search {
            position: relative;
            flex: 1;
            max-width: 560px
        }

        .search input {
            padding: 10px 12px 10px 36px;
            background: #f6f8fc
        }

        .search:before {
            content: '⌕';
            position: absolute;
            left: 13px;
            top: 9px;
            color: #65718a;
            font-size: 17px
        }

        .top-actions {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 10px
        }

        .logout {
            background: #f4f7fc;
            color: #102047;
            padding: 8px 11px;
            font-size: 12px
        }

        .shell main {
            width: min(1280px, calc(100% - 34px));
            margin: 22px auto 48px
        }

        .shell header {
            display: none
        }

        @media(max-width:800px) {
            .sidebar {
                position: static;
                width: 100%;
                padding: 14px 16px
            }

            .sidebar nav {
                display: flex;
                overflow: auto;
                gap: 5px
            }

            .menu-link {
                white-space: nowrap;
                margin: 0
            }

            .sidebar-footer {
                display: none
            }

            .shell {
                margin: 0;
                width: 100%
            }

            .topbar {
                padding: 0 15px;
                flex-wrap: wrap
            }

            .topbar-title {
                display: none
            }

            .search {
                flex-basis: 100%;
                max-width: none;
                padding: 8px 0
            }

            .shell main {
                width: calc(100% - 20px);
                margin-top: 12px
            }
        }
    </style>
    <aside class="sidebar">
        <div class="brand">SalesGo Admin</div>
        <div class="sub">Master Data SFA<br><?php echo e($actor->role->name); ?> · <?php echo e($branch->code); ?></div>
        <nav><a class="menu-link" href="#users">Pengguna</a><a class="menu-link" href="#outlets">Outlet</a><a
                class="menu-link" href="#products">Produk</a><a class="menu-link" href="#promotions">Promosi</a><a
                class="menu-link" href="#routes">Master Rute</a><a class="menu-link" href="#divisions">Divisi</a>
            <?php if($actor->role->slug === 'it'): ?>
                <a class="menu-link" href="#branches">Cabang</a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer"><?php echo e($actor->name); ?><br><?php echo e($actor->employee_code); ?></div>
    </aside>
    <div class="shell">
        <div class="topbar">
            <div class="topbar-title">Master Data SFA</div>
            <div class="search"><input id="master-search" placeholder="Cari fitur portal..."></div>
            <div class="top-actions">
                <?php if($actor->role->slug === 'it'): ?>
                    <form method="get" style="padding:0"><select name="branch_id" onchange="this.form.submit()">
                            <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($item->id); ?>" <?php if($item->id === $branch->id): echo 'selected'; endif; ?>><?php echo e($item->code); ?> ·
                                    <?php echo e($item->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select></form>
                <?php endif; ?>
                <form method="post" action="<?php echo e(route('internal.master-data.logout')); ?>" style="padding:0"><?php echo csrf_field(); ?><button
                        class="logout">Keluar</button></form>
            </div>
        </div>
        <main>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px">
                <div><strong><?php echo e($actor->name); ?></strong><br><small><?php echo e($actor->employee_code); ?></small></div>
                <?php if($actor->role->slug === 'it'): ?>
                    <form method="get" style="padding:0"><select name="branch_id" onchange="this.form.submit()">
                            <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($item->id); ?>" <?php if($item->id === $branch->id): echo 'selected'; endif; ?>><?php echo e($item->code); ?> ·
                                    <?php echo e($item->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select></form>
                <?php endif; ?>
            </div>
            <?php if(session('success')): ?>
                <div class="alert success"><?php echo e(session('success')); ?></div>
            <?php endif; ?>
            <?php if($errors->any()): ?>
                <div class="alert errors">
                    <ul>
                        <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            <?php endif; ?>
            <section class="forms">
                <details class="card" open>
                    <summary class="head">
                        <h2>Tambah Pengguna</h2>
                        <p>Operasional hanya dapat membuat role Operasional, Sales, dan Marketing.</p>
                    </summary>
                    <form method="post" action="<?php echo e(route('internal.master-data.store', 'user')); ?>"><?php echo csrf_field(); ?><input
                            type="hidden" name="branch_id" value="<?php echo e($branch->id); ?>">
                        <div class="two">
                            <div class="field"><label>Nama</label><input name="name" required></div>
                            <div class="field"><label>Username</label><input name="username" required></div>
                        </div>
                        <div class="two">
                            <div class="field"><label>Role</label><select name="role_id">
                                    <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <?php if($actor->role->slug === 'it' || in_array($role->slug, ['operational', 'sales', 'marketing'])): ?>
                                            <option value="<?php echo e($role->id); ?>"><?php echo e($role->name); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select></div>
                            <div class="field"><label>Divisi</label><select name="sales_division_id">
                                    <?php $__currentLoopData = $divisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $division): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($division->id); ?>"><?php echo e($division->code); ?> ·
                                            <?php echo e($division->name); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select></div>
                        </div>
                        <div class="two">
                            <div class="field"><label>Email</label><input name="email" type="email"></div>
                            <div class="field"><label>Maks. perangkat</label><input name="max_devices" type="number"
                                    value="1" min="1" max="10"></div>
                        </div>
                        <div class="field"><label>Password awal</label><input name="password" type="password"
                                minlength="8" required></div><button>Simpan Pengguna</button>
                    </form>
                </details>
                <details class="card">
                    <summary class="head">
                        <h2>Tambah Outlet</h2>
                        <p>Kode outlet dibuat otomatis sesuai cabang.</p>
                    </summary>
                    <form method="post" action="<?php echo e(route('internal.master-data.store', 'outlet')); ?>"><?php echo csrf_field(); ?><input
                            type="hidden" name="branch_id" value="<?php echo e($branch->id); ?>">
                        <div class="two">
                            <div class="field"><label>Nama</label><input name="name" required></div>
                            <div class="field"><label>Tipe</label><input name="type" required></div>
                        </div>
                        <div class="field"><label>Alamat</label>
                            <textarea name="address" required></textarea>
                        </div>
                        <div class="two">
                            <div class="field"><label>Pemilik</label><input name="owner_name"></div>
                            <div class="field"><label>Telepon</label><input name="phone"></div>
                        </div>
                        <div class="two">
                            <div class="field"><label>Latitude</label><input name="latitude" type="number"
                                    step="any"></div>
                            <div class="field"><label>Longitude</label><input name="longitude" type="number"
                                    step="any"></div>
                        </div>
                        <div class="field"><label>Divisi layanan</label><select name="division_ids[]" multiple>
                                <?php $__currentLoopData = $divisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $division): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($division->id); ?>"><?php echo e($division->code); ?> ·
                                        <?php echo e($division->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select></div><button>Simpan Outlet</button>
                    </form>
                </details>
                <details class="card">
                    <summary class="head">
                        <h2>Tambah Produk</h2>
                        <p>Harga dan stok server menjadi acuan order mobile.</p>
                    </summary>
                    <form method="post" action="<?php echo e(route('internal.master-data.store', 'product')); ?>"><?php echo csrf_field(); ?><input
                            type="hidden" name="branch_id" value="<?php echo e($branch->id); ?>">
                        <div class="two">
                            <div class="field"><label>SKU</label><input name="sku" required></div>
                            <div class="field"><label>Divisi</label><select name="sales_division_id">
                                    <?php $__currentLoopData = $divisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $division): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($division->id); ?>"><?php echo e($division->code); ?> ·
                                            <?php echo e($division->name); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select></div>
                        </div>
                        <div class="two">
                            <div class="field"><label>Nama</label><input name="name" required></div>
                            <div class="field"><label>Merek</label><input name="brand"></div>
                        </div>
                        <div class="two">
                            <div class="field"><label>Varian</label><input name="variant"></div>
                            <div class="field"><label>UOM</label><input name="uom" placeholder="PCS"></div>
                        </div>
                        <div class="two">
                            <div class="field"><label>Harga</label><input name="price" type="number"
                                    step="0.01" min="0" required></div>
                            <div class="field"><label>Stok</label><input name="stock" type="number"
                                    min="0" required></div>
                        </div>
                        <div class="two">
                            <div class="field"><label>Kategori</label><input name="category"></div>
                            <div class="field"><label>Barcode</label><input name="barcode"></div>
                        </div><button>Simpan Produk</button>
                    </form>
                </details>
                <details class="card">
                    <summary class="head">
                        <h2>Tambah Promosi</h2>
                        <p>Kuota, budget, dan periode divalidasi saat order.</p>
                    </summary>
                    <form method="post" action="<?php echo e(route('internal.master-data.store', 'promotion')); ?>"><?php echo csrf_field(); ?><input
                            type="hidden" name="branch_id" value="<?php echo e($branch->id); ?>">
                        <div class="two">
                            <div class="field"><label>Kode</label><input name="code" required></div>
                            <div class="field"><label>Nama</label><input name="name" required></div>
                        </div>
                        <div class="two">
                            <div class="field"><label>Jenis</label><select name="type">
                                    <option>Percentage</option>
                                    <option>Fixed</option>
                                </select></div>
                            <div class="field"><label>Nilai</label><input name="value" type="number"
                                    step="0.01" min="0" required></div>
                        </div>
                        <div class="two">
                            <div class="field"><label>Minimum order</label><input name="minimum_order_amount"
                                    type="number" step="0.01" min="0"></div>
                            <div class="field"><label>Maks. diskon</label><input name="maximum_discount"
                                    type="number" step="0.01" min="0"></div>
                        </div>
                        <div class="two">
                            <div class="field"><label>Kuota pusat</label><input name="quota_total" type="number"
                                    min="1"></div>
                            <div class="field"><label>Budget</label><input name="budget_amount" type="number"
                                    step="0.01" min="0"></div>
                        </div>
                        <div class="two">
                            <div class="field"><label>Mulai</label><input name="starts_at" type="datetime-local"
                                    required></div>
                            <div class="field"><label>Selesai</label><input name="ends_at" type="datetime-local"
                                    required></div>
                        </div>
                        <div class="field"><label>Keterangan</label>
                            <textarea name="description"></textarea>
                        </div><button>Simpan Promosi</button>
                    </form>
                </details>
                <details class="card">
                    <summary class="head">
                        <h2>Tambah Rute</h2>
                        <p>Kombinasi outlet, sales, hari, dan minggu tidak boleh duplikat.</p>
                    </summary>
                    <form method="post" action="<?php echo e(route('internal.master-data.store', 'route')); ?>"><?php echo csrf_field(); ?><input
                            type="hidden" name="branch_id" value="<?php echo e($branch->id); ?>">
                        <div class="field"><label>Outlet</label><select name="outlet_id">
                                <?php $__currentLoopData = $outlets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $outlet): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($outlet->id); ?>"><?php echo e($outlet->code); ?> · <?php echo e($outlet->name); ?>

                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select></div>
                        <div class="field"><label>Sales / pengguna</label><select name="sales_id">
                                <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($user->id); ?>"><?php echo e($user->employee_code); ?> ·
                                        <?php echo e($user->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select></div>
                        <div class="two">
                            <div class="field"><label>Hari</label><select name="day_of_week">
                                    <option value="1">Senin</option>
                                    <option value="2">Selasa</option>
                                    <option value="3">Rabu</option>
                                    <option value="4">Kamis</option>
                                    <option value="5">Jumat</option>
                                    <option value="6">Sabtu</option>
                                    <option value="7">Minggu</option>
                                </select></div>
                            <div class="field"><label>Minggu</label><select name="week_of_month">
                                    <option value="1">Minggu 1</option>
                                    <option value="2">Minggu 2</option>
                                    <option value="3">Minggu 3</option>
                                    <option value="4">Minggu 4</option>
                                </select></div>
                        </div><button>Simpan Rute</button>
                    </form>
                </details>
                <details class="card">
                    <summary class="head">
                        <h2>Tambah Divisi</h2>
                        <p>Kode divisi dipakai pada user dan produk.</p>
                    </summary>
                    <form method="post" action="<?php echo e(route('internal.master-data.store', 'division')); ?>"><?php echo csrf_field(); ?><input
                            type="hidden" name="branch_id" value="<?php echo e($branch->id); ?>">
                        <div class="two">
                            <div class="field"><label>Kode (2 digit)</label><input name="code" maxlength="2"
                                    required></div>
                            <div class="field"><label>Nama</label><input name="name" required></div>
                        </div><button>Simpan Divisi</button>
                    </form>
                </details>
                <?php if($actor->role->slug === 'it'): ?>
                    <details class="card">
                        <summary class="head">
                            <h2>Tambah Cabang</h2>
                            <p>Khusus IT.</p>
                        </summary>
                        <form method="post" action="<?php echo e(route('internal.master-data.store', 'branch')); ?>"><?php echo csrf_field(); ?><input
                                type="hidden" name="branch_id" value="<?php echo e($branch->id); ?>">
                            <div class="two">
                                <div class="field"><label>Kode (3 digit)</label><input name="code" maxlength="3"
                                        required></div>
                                <div class="field"><label>Nama</label><input name="name" required></div>
                            </div><button>Simpan Cabang</button>
                        </form>
                    </details>
                <?php endif; ?>
            </section>
            <section class="tables">
                <?php $__currentLoopData = ['users' => $users, 'outlets' => $outlets, 'products' => $products, 'promotions' => $promotions, 'routes' => $routes]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group => $rows): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <article class="card">
                        <div class="head">
                            <h2><?php echo e(ucfirst($group)); ?> (<?php echo e($rows->count()); ?>)</h2>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Informasi</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <?php
                                            $active =
                                                $group === 'outlets' ? $row->status === 'Active' : $row->is_active;
                                            $entity = $group === 'users' ? 'user' : rtrim($group, 's');
                                        ?>
                                        <tr>
                                            <td><?php echo e($group === 'users' ? $row->employee_code : ($group === 'outlets' ? $row->code : ($group === 'products' ? $row->sku : ($group === 'promotions' ? $row->code : $row->id)))); ?>

                                            </td>
                                            <td>
                                                <?php if($group === 'users'): ?>
                                                    <?php echo e($row->name); ?><br><small><?php echo e($row->role?->name); ?> ·
                                                        <?php echo e($row->division?->name); ?></small>
                                                <?php elseif($group === 'outlets'): ?>
                                                    <?php echo e($row->name); ?><br><small><?php echo e($row->address); ?></small>
                                                <?php elseif($group === 'products'): ?>
                                                    <?php echo e($row->name); ?><br><small>Rp
                                                        <?php echo e(number_format($row->price, 0, ',', '.')); ?> · stok
                                                        <?php echo e($row->stock); ?></small>
                                                <?php elseif($group === 'promotions'): ?>
                                                    <?php echo e($row->name); ?><br><small><?php echo e($row->type); ?>

                                                        <?php echo e($row->value); ?></small>
                                                    <?php else: ?><?php echo e($row->outlet?->name); ?><br><small><?php echo e($row->sales?->name); ?>

                                                        · hari <?php echo e($row->day_of_week); ?> · minggu
                                                        <?php echo e($row->week_of_month); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><span
                                                    class="badge <?php echo e($active ? 'on' : 'off'); ?>"><?php echo e($active ? 'Aktif' : 'Nonaktif'); ?></span>
                                            </td>
                                            <td>
                                                <form class="inline" method="post"
                                                    action="<?php echo e(route('internal.master-data.toggle', [$entity, $row->id])); ?>">
                                                    <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?><input type="hidden" name="branch_id"
                                                        value="<?php echo e($branch->id); ?>"><input type="hidden"
                                                        name="active" value="<?php echo e($active ? 0 : 1); ?>"><button
                                                        class="<?php echo e($active ? 'danger' : 'secondary'); ?>"><?php echo e($active ? 'Nonaktifkan' : 'Aktifkan'); ?></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr>
                                            <td colspan="4"><small>Belum ada data.</small></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </section>
        </main>
    </div>
    <script>
        const portalStyle = document.createElement('style');
        portalStyle.textContent =
            `.menu-link:before{display:inline-grid;place-items:center;width:22px;height:22px;margin-right:9px;border-radius:7px;background:#ffffff1f;font-size:13px}.menu-link[href="#users"]:before{content:'\\1F464'}.menu-link[href="#outlets"]:before{content:'\\1F3EA'}.menu-link[href="#products"]:before{content:'\\1F4E6'}.menu-link[href="#promotions"]:before{content:'\\1F3F7'}.menu-link[href="#routes"]:before{content:'\\1F5FA'}.menu-link[href="#divisions"]:before{content:'\\2699'}.menu-link[href="#branches"]:before{content:'\\1F3E2'}.menu-link.active{background:#fff;color:#1258cc;box-shadow:0 8px 20px #00307a33}.sidebar-toggle{width:38px;height:38px;padding:0;background:#eaf1ff;color:#1458cb;font-size:19px;line-height:1}.section-card[hidden]{display:none!important}body.sidebar-collapsed .sidebar{transform:translateX(-100%)}body.sidebar-collapsed .shell{margin-left:0;width:100%}.sidebar{transition:transform .2s ease}.shell{transition:margin-left .2s ease,width .2s ease}.detail-button{padding:6px 9px;margin-right:6px;background:#edf3ff;color:#1659c8;font-size:11px}.detail-dialog{border:0;border-radius:16px;width:min(520px,calc(100% - 32px));padding:0;box-shadow:0 20px 60px #10204755}.detail-dialog::backdrop{background:#10204788}.detail-body{padding:22px}.detail-body h2{font-size:18px;margin-bottom:16px}.detail-row{display:grid;grid-template-columns:130px 1fr;gap:10px;padding:10px 0;border-bottom:1px solid #edf1f7}.detail-label{color:#65718a;font-size:12px;font-weight:bold}.detail-close{float:right;background:#f1f5fb;color:#173261;padding:7px 10px}.product-hero{width:100%;height:190px;object-fit:contain;background:linear-gradient(135deg,#edf4ff,#f8fbff);border-radius:13px;margin:0 0 17px}@media(max-width:800px){body.sidebar-collapsed .sidebar{display:none}}`;
        document.head.appendChild(portalStyle);
        portalStyle.textContent +=
            `.forms details{border:1px solid #dce6f6}.forms details summary{padding:18px 20px;background:linear-gradient(180deg,#fff,#f8fbff);display:block}.forms details summary:after{content:"Tampilkan form";float:right;background:#eaf1ff;color:#1558c9;border-radius:99px;padding:6px 10px;font-size:11px;font-weight:bold;margin-top:-4px}.forms details[open] summary:after{content:"Sembunyikan form";background:#f0f4fb;color:#516078}.forms details form{border-top:1px solid #e2e9f5}.forms details button[type="submit"]{width:100%;margin-top:8px;padding:12px 14px;box-shadow:0 8px 18px #1768f233}.menu-upload{margin-top:13px;padding-top:13px;border-top:1px solid #ffffff30}.menu-upload:before{content:"\\2B06"}.sidebar{overflow-y:auto}@media(max-width:900px){.sidebar{width:210px}.shell{margin-left:210px;width:calc(100% - 210px)}.topbar{padding:0 18px}.shell main{width:calc(100% - 24px)}.forms{grid-template-columns:1fr}.table-wrap{max-width:calc(100vw - 250px)}}@media(max-width:640px){body{display:block}.sidebar{position:fixed;width:min(280px,82vw);box-shadow:8px 0 30px #10204744}.sidebar nav{display:block;overflow:visible}.menu-link{white-space:normal;margin-bottom:6px}.shell{margin-left:0;width:100%}.topbar{min-height:64px;gap:10px;padding:8px 12px}.top-actions{gap:7px}.top-actions select{max-width:125px}.logout{padding:8px}.table-wrap{max-width:calc(100vw - 20px)}.detail-row{grid-template-columns:1fr;gap:3px}.forms details summary{padding:15px}.forms details summary:after{font-size:10px}}`;
        portalStyle.textContent +=
            `.menu-link.active{background:#fff;color:#1258cc;box-shadow:0 8px 20px #00307a33}.menu-link.active:before{background:#e8f0ff}`;
        portalStyle.textContent +=
            `.head{display:flex;align-items:center;gap:12px}.head .list-search{margin-left:auto;width:min(280px,45%);padding:8px 10px;background:#f6f8fc;border:1px solid #d7e0ef;border-radius:8px;font:inherit;font-size:12px}@media(max-width:640px){.head{display:block}.head .list-search{width:100%;margin:12px 0 0}}`;
        const responsiveStyle = document.createElement('style');
        responsiveStyle.textContent =
            '@media(max-width:900px){.sidebar nav{display:block!important;overflow:visible!important}.sidebar .menu-link{white-space:normal!important;margin:0 0 7px!important}.sidebar{overflow-y:auto!important}}';
        document.head.appendChild(responsiveStyle);
        const masterSearch = document.getElementById('master-search');
        const menuTitles = {
            users: 'Pengguna',
            outlets: 'Outlet',
            products: 'Produk',
            promotions: 'Promosi',
            routes: 'Rute',
            divisions: 'Divisi',
            branches: 'Cabang'
        };
        const sectionAliases = {
            users: 'users',
            outlets: 'outlets',
            products: 'products',
            promotions: 'promotions',
            routes: 'routes',
            divisions: 'divisions',
            branches: 'branches'
        };
        const headingToSection = (heading) => {
            const text = heading.toLowerCase();
            if (text.includes('pengguna') || text.includes('users')) return 'users';
            if (text.includes('outlet')) return 'outlets';
            if (text.includes('produk') || text.includes('products')) return 'products';
            if (text.includes('promosi') || text.includes('promotions')) return 'promotions';
            if (text.includes('rute') || text.includes('routes')) return 'routes';
            if (text.includes('divisi')) return 'divisions';
            if (text.includes('cabang')) return 'branches';
            return null;
        };

        document.querySelectorAll('details.card, article.card').forEach((card) => {
            const heading = card.querySelector('h2')?.textContent ?? '';
            const section = headingToSection(heading);
            if (section) {
                card.dataset.section = section;
                card.classList.add('section-card');
            }
        });

        const branchRows = <?php echo json_encode($branchRows, 15, 512) ?>;
        const element = (tag, text, className) => {
            const node = document.createElement(tag);
            if (text !== undefined) node.textContent = text;
            if (className) node.className = className;
            return node;
        };
        const branchTable = element('article', undefined, 'card section-card');
        branchTable.dataset.section = 'branches';
        const branchHead = element('div', undefined, 'head');
        branchHead.append(element('h2', `Daftar Cabang (${branchRows.length})`), element('p',
            'Cabang yang dapat diakses oleh akun ini.'));
        const branchWrap = element('div', undefined, 'table-wrap');
        const branchNativeTable = element('table');
        const branchThead = element('thead');
        const branchHeaderRow = element('tr');
        ['Kode', 'Nama Cabang', 'Geofence', 'Status', 'Aksi'].forEach((label) => branchHeaderRow.append(element('th',
            label)));
        branchThead.append(branchHeaderRow);
        const branchTbody = element('tbody');
        branchRows.forEach((item) => {
            const row = element('tr');
            row.append(element('td', item.code), element('td', item.name), element('td', item.radius ?
                `${item.radius} m` : '-'));
            const status = element('span', item.active ? 'Aktif' : 'Nonaktif',
                `badge ${item.active ? 'on' : 'off'}`);
            const statusCell = element('td');
            statusCell.append(status);
            row.append(statusCell);
            const actionCell = element('td');
            const detail = element('button', 'Detail', 'detail-button');
            detail.type = 'button';
            detail.dataset.detailUrl = `<?php echo e(url('/internal/master-data/branch')); ?>/${item.id}`;
            actionCell.append(detail);
            row.append(actionCell);
            branchTbody.append(row);
        });
        if (!branchRows.length) {
            const row = element('tr');
            const cell = element('td', 'Belum ada cabang.');
            cell.colSpan = 5;
            row.append(cell);
            branchTbody.append(row);
        }
        branchNativeTable.append(branchThead, branchTbody);
        branchWrap.append(branchNativeTable);
        branchTable.append(branchHead, branchWrap);
        document.querySelector('.tables')?.prepend(branchTable);

        const detailDialog = document.createElement('dialog');
        detailDialog.className = 'detail-dialog';
        document.body.appendChild(detailDialog);
        const showDetail = (title, rows, imageUrl) => {
            detailDialog.replaceChildren();
            const body = element('div', undefined, 'detail-body');
            const close = element('button', 'Tutup', 'detail-close');
            close.type = 'button';
            close.addEventListener('click', () => detailDialog.close());
            body.append(close, element('h2', title));
            if (imageUrl) {
                const image = document.createElement('img');
                image.className = 'product-hero';
                image.src = imageUrl;
                image.alt = title;
                image.addEventListener('error', () => image.remove());
                body.append(image);
            }
            rows.forEach(([label, value]) => {
                const row = element('div', undefined, 'detail-row');
                row.append(element('span', label, 'detail-label'), element('span', value || '-'));
                body.append(row);
            });
            detailDialog.append(body);
            detailDialog.showModal();
        };

        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-detail-url]');
            if (!button) return;
            button.disabled = true;
            try {
                const response = await fetch(button.dataset.detailUrl, {
                    headers: {
                        Accept: 'application/json'
                    }
                });
                if (!response.ok) throw new Error('Data detail tidak dapat dimuat.');
                const payload = await response.json();
                showDetail(payload.title ?? 'Detail data master', Object.entries(payload.data ?? {}), payload
                    .imageUrl);
            } catch (error) {
                alert(error.message || 'Data detail tidak dapat dimuat.');
            } finally {
                button.disabled = false;
            }
        });

        document.querySelectorAll('.tables tbody tr').forEach((row) => {
            const cells = row.querySelectorAll('td');
            if (cells.length < 4 || !cells[0].textContent.trim()) return;
            const detail = element('button', 'Detail', 'detail-button');
            detail.type = 'button';
            const action = cells[3].querySelector('form')?.getAttribute('action');
            if (!action) return;
            detail.dataset.detailUrl = action.replace(/\/status$/, '');
            cells[3].prepend(detail);
        });

        const topbar = document.querySelector('.topbar');
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'sidebar-toggle';
        toggle.title = 'Tampilkan atau sembunyikan menu';
        toggle.setAttribute('aria-label', toggle.title);
        toggle.textContent = '☰';
        topbar?.prepend(toggle);
        toggle.addEventListener('click', () => document.body.classList.toggle('sidebar-collapsed'));

        const canUploadApk = <?php echo json_encode($actor->role?->slug === 'it', 15, 512) ?>;
        if (canUploadApk) {
            const releaseLink = element('a', 'Upload APK', 'menu-link menu-upload');
            releaseLink.href = '<?php echo e(route('internal.app-releases.index')); ?>';
            document.querySelector('.sidebar nav')?.append(releaseLink);
        }

        const featureOptions = document.createElement('datalist');
        featureOptions.id = 'portal-feature-options';
        document.querySelectorAll('.sidebar .menu-link').forEach((link) => {
            const option = document.createElement('option');
            option.value = link.textContent.trim();
            featureOptions.append(option);
        });
        document.body.append(featureOptions);
        masterSearch?.setAttribute('list', featureOptions.id);
        masterSearch?.addEventListener('change', () => {
            const selected = masterSearch.value.trim().toLowerCase();
            const link = [...document.querySelectorAll('.sidebar .menu-link')]
                .find((item) => item.textContent.trim().toLowerCase() === selected);
            if (!link) return;
            masterSearch.value = '';
            link.click();
        });

        const selectSection = (section) => {
            if (typeof searchResults !== 'undefined') searchResults.hidden = true;
            document.querySelectorAll('[data-section]').forEach((card) => {
                card.hidden = card.dataset.section !== section;
            });
            document.querySelectorAll('.tables tbody tr').forEach((row) => {
                row.hidden = false;
            });
            document.querySelectorAll('.menu-link').forEach((link) => {
                link.classList.toggle('active', link.getAttribute('href') === `#${section}`);
            });
            const title = menuTitles[section] ?? 'Master Data SFA';
            const titleNode = document.querySelector('.topbar-title');
            if (titleNode) titleNode.textContent = title;
        };

        const searchResults = element('article', undefined, 'card section-card');
        searchResults.dataset.searchResults = 'true';
        const searchResultsHead = element('div', undefined, 'head');
        const searchResultsTitle = element('h2', 'Hasil pencarian');
        const searchResultsDescription = element('p', 'Cari kode, nama, alamat, SKU, divisi, atau informasi master lainnya.');
        searchResultsHead.append(searchResultsTitle, searchResultsDescription);
        const searchResultsWrap = element('div', undefined, 'table-wrap');
        const searchResultsTable = element('table');
        const searchResultsThead = element('thead');
        const searchResultsHeader = element('tr');
        ['Master', 'Kode', 'Informasi', 'Status'].forEach((label) => searchResultsHeader.append(element('th', label)));
        searchResultsThead.append(searchResultsHeader);
        const searchResultsBody = element('tbody');
        searchResultsTable.append(searchResultsThead, searchResultsBody);
        searchResultsWrap.append(searchResultsTable);
        searchResults.append(searchResultsHead, searchResultsWrap);
        searchResults.hidden = true;
        document.querySelector('.tables')?.prepend(searchResults);

        const renderSearchResults = (query) => {
            searchResultsBody.replaceChildren();
            let count = 0;
            document.querySelectorAll('.tables article.section-card:not([data-search-results])').forEach((card) => {
                const section = card.dataset.section;
                card.querySelectorAll('tbody tr').forEach((sourceRow) => {
                    const cells = sourceRow.querySelectorAll('td');
                    if (!cells.length || !sourceRow.textContent.toLowerCase().includes(query)) return;
                    const resultRow = element('tr');
                    resultRow.append(
                        element('td', menuTitles[section] ?? 'Master'),
                        element('td', cells[0]?.innerText.trim() ?? '-'),
                        element('td', cells[1]?.innerText.trim() ?? '-'),
                        element('td', cells[2]?.innerText.trim() ?? '-'),
                    );
                    searchResultsBody.append(resultRow);
                    count += 1;
                });
            });
            if (!count) {
                const emptyRow = element('tr');
                const emptyCell = element('td', 'Tidak ada data master yang sesuai.');
                emptyCell.colSpan = 4;
                emptyRow.append(emptyCell);
                searchResultsBody.append(emptyRow);
            }
            searchResultsTitle.textContent = `Hasil pencarian (${count})`;
        };

        document.querySelectorAll('.tables article.section-card:not([data-search-results])').forEach((card) => {
            const heading = card.querySelector('.head');
            const tableBody = card.querySelector('tbody');
            if (!heading || !tableBody) return;
            const label = card.querySelector('h2')?.textContent.replace(/\s*\(.*\)$/, '') ?? 'data';
            const filter = document.createElement('input');
            filter.type = 'search';
            filter.className = 'list-search';
            filter.placeholder = `Cari ${label}...`;
            filter.setAttribute('aria-label', `Cari item ${label}`);
            filter.addEventListener('input', () => {
                const query = filter.value.trim().toLowerCase();
                tableBody.querySelectorAll('tr').forEach((row) => {
                    row.hidden = query !== '' && !row.textContent.toLowerCase().includes(query);
                });
            });
            heading.append(filter);
        });

        let selectedSection = sectionAliases[location.hash.replace('#', '')] ?? 'users';
        masterSearch?.addEventListener('input', () => {
            const query = masterSearch.value.trim().toLowerCase();
            document.querySelectorAll('.sidebar .menu-link').forEach((link) => {
                link.hidden = query !== '' && !link.textContent.toLowerCase().includes(query);
            });
        });

        document.querySelectorAll('.menu-link').forEach((link) => {
            link.addEventListener('click', (event) => {
                const section = sectionAliases[link.getAttribute('href')?.replace('#', '')];
                if (!section) return;
                event.preventDefault();
                selectedSection = section;
                masterSearch.value = '';
                selectSection(section);
                history.replaceState(null, '', `#${section}`);
                if (window.innerWidth <= 800) document.body.classList.add('sidebar-collapsed');
            });
        });

        selectSection(sectionAliases[location.hash.replace('#', '')] ?? 'users');
    </script>
</body>

</html>
<?php /**PATH D:\SalesGo\SalesGo-Api\resources\views/internal-master-data/index.blade.php ENDPATH**/ ?>