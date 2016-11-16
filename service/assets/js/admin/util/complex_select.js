// 初始化三级联动，参数分别为：
// a:一级select控件的id
// k:二级select控件的id
// h:三级select控件的id，设置为null则退化为二级联动
// p:一级信息所在数组名
// q:二级信息所在数组名
// r:三级信息所在数组名，设置为null则退化为二级联动
// d:默认的一级信息值
// b:默认的二级信息值
// l:默认的三级信息值，设置为null则退化为二级联动
function initComplexArea(a, k, h, p, q, r, d, b, l) {
    var f = initComplexArea.arguments;
    var m = document.getElementById(a);
    var o = document.getElementById(k);
    var n = document.getElementById(h);
    var e = 0;
    var c = 0;

    $('#' + a).change(function(){
        changeComplexProvince(this.value, q, k, h);
    });
    $('#' + k).change(function(){
        changeCity($('#' + a).val(), this.value, r, h);
    });

    if (p != undefined) {
        if (d != undefined) {
            d = parseInt(d);
        }
        else {
            d = 0;
        }
        if (b != undefined) {
            b = parseInt(b);
        }
        else {
            b = 0;
        }
        if (l != undefined) {
            l = parseInt(l);
        }
        else {
            l = 0
        }
        for (e = 0; e < p.length; e++) {
            if (p[e] == undefined) {
                continue;
            }
            if (f[6]) {
                if (f[6] == true) {
                    if (e == 0) {
                        continue
                    }
                }
            }
            m[c] = new Option(p[e], e);
            if (d == e) {
                m[c].selected = true;
            }
            c++
        }
        if (q[d] != undefined) {
            c = 0; for (e = 0; e < q[d].length; e++) {
                if (q[d][e] == undefined) { continue }
                if (f[6]) {
                    if ((f[6] == true) && (d != 71) && (d != 81) && (d != 82)) {
                        if ((e % 100) == 0) { continue }
                    }
                } o[c] = new Option(q[d][e], e);
                if (b == e) { o[c].selected = true } c++
            }
        }
        if (r != undefined && r[d] != undefined && r[d][b] != undefined) {
            n[0] = new Option("请选择 ", 0);
            c = 0; for (e = 0; e < r[d][b].length; e++) {
                if (r[d][b][e] == undefined) { continue }
                //if (f[6]) {
                //    if ((f[6] == true) && (d != 71) && (d != 81) && (d != 82)) {
                //        if ((e % 100) == 0) { continue }
                //    }
                //}
                n[c] = new Option(r[d][b][e], e);
                if (l == e) { n[c].selected = true } c++
            }
        }
    }
}
function changeComplexProvince(f, k, e, d) {
    var c = changeComplexProvince.arguments;
    var h = document.getElementById(e);
    var g = document.getElementById(d);
    var b = 0;
    var a = 0;
    removeOptions(h);
    f = parseInt(f);
    if (k[f] != undefined) {
        for (b = 0; b < k[f].length; b++) {
            if (k[f][b] == undefined) { continue }
            if (c[3]) { if ((c[3] == true) && (f != 71) && (f != 81) && (f != 82)) { if ((b % 100) == 0) { continue } } }
            h[a] = new Option(k[f][b], b); a++
        }
    }
    if (g != undefined) {
        removeOptions(g);
        g[0] = new Option("请选择 ", 0);
    }

    if (f == 11 || f == 12 || f == 31 || f == 71 || f == 50 || f == 81 || f == 82) {
        if ($("#" + d))
        { $("#" + d).hide(); }
    }
    else {
        if ($("#" + d)) { $("#" + d).show(); }
    }
}


function changeCity(p, c, t, a) {
    $("#" + a).html('<option value="0" >请选择</option>');
    $("#" + a).unbind("change");
    p = parseInt(p);
    c = parseInt(c);
    if (t != undefined && t[p] != undefined) {
        var _d = t[p][c];
        var str = "";
        str += "<option value='0' >请选择</option>";
        if (_d != undefined) {
            for (var i in _d) {
                if (_d[i] == undefined) continue;
                str += "<option value='" + i + "' >" + _d[i] + "</option>";
            }
        }
        $("#" + a).html(str);
    }
}

function removeOptions(c) {
    if ((c != undefined) && (c.options != undefined)) {
        var a = c.options.length;
        for (var b = 0; b < a; b++) {
            c.options[0] = null;
        }
    }
}
