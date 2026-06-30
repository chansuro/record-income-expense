<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaxiTax Account</title>

<link href="{{ asset('admin/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">
        <link
            href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
            rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">


<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, sans-serif;
}

body{
    background:#f5f7fb;
    color:#111;
}

/* Layout */

/* Card */
.card{
    background:#fff;
    border-radius:16px;
    padding:28px;
    box-shadow:0 6px 20px rgba(0,0,0,0.08);
    margin-bottom:22px;
}

/* Headings */
h2{
    margin-bottom:16px;
}

/* Inputs */
input{
    width:100%;
    padding:14px;
    border:1px solid #ddd;
    border-radius:12px;
    margin-bottom:12px;
    font-size:15px;
}

/* Primary Button */
.btn{
    width:100%;
    padding:14px;
    background:#2563eb;
    color:white;
    border:none;
    border-radius:12px;
    font-size:15px;
    font-weight:600;
    cursor:pointer;
    transition:.2s;
}

.btn:hover{
    background:#1d4ed8;
}

/* Profile */
.profile{
    text-align:center;
}

.avatar{
    width:80px;
    height:80px;
    border-radius:50%;
    background:#2563eb;
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:28px;
    font-weight:bold;
    margin:0 auto 10px;
}

.email{
    color:#666;
    font-size:14px;
}

/* Rows */
.row{
    display:flex;
    justify-content:space-between;
    padding:12px 0;
    border-bottom:1px solid #eee;
    font-size:14px;
}

/* Billing */
.plan{
    background:#eff6ff;
    border:1px solid #bfdbfe;
    padding:16px;
    border-radius:12px;
    margin-bottom:15px;
}

.price{
    font-size:22px;
    font-weight:bold;
    color:#2563eb;
    margin-top:6px;
}

/* Logout */
.logout-btn{
    width:100%;
    padding:14px;
    background:#fff;
    color:#ef4444;
    border:1px solid #fecaca;
    border-radius:12px;
    font-size:15px;
    font-weight:600;
    cursor:pointer;
    transition:.2s;
}

.logout-btn:hover{
    background:#fef2f2;
    border-color:#ef4444;
}

/* App Download Buttons */
.downloads{
    display:flex;
    gap:12px;
    margin-top:10px;
}

.store-btn{
    flex:1;
    padding:14px;
    border-radius:12px;
    border:none;
    cursor:pointer;
    font-weight:600;
    font-size:14px;
    color:white;
    transition:.2s;
}

.android{
    background:#22c55e;
}

.android:hover{
    background:#16a34a;
}

.ios{
    background:#111;
}

.ios:hover{
    background:#000;
}
</style>
</head>

<body>

<div class="container">

{{ $slot }}

</div>

<script>
function logout(){
    localStorage.clear();
    sessionStorage.clear();
    alert("Logged out successfully");
    window.location.href = "login.html";
}
</script>

</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
</html>