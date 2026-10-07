// 참살이사회적협동조합 홈페이지 스크립트
// - 모든 페이지 공통: 머리글/바닥글/모바일 메뉴
// - 페이지별 기능: 해당 요소가 있는 페이지에서만 동작합니다.
// - 데이터는 JSON 파일(businesses/products/notices/press/photos/config)을 읽습니다.
(function(){
  'use strict';
  var BASE = '';
  var $ = function(id){ return document.getElementById(id); };
  // 관리자 페이지에서 저장한 글은 이미 HTML 형식(줄바꿈 <br>, 링크, 사진 포함)이므로 그대로 사용합니다.
  var esc = function(s){ return s == null ? '' : String(s); };
  var tmp = document.createElement('div');
  var plain = function(s){ tmp.innerHTML = s == null ? '' : String(s); return tmp.textContent.replace(/\s+/g, ' ').trim(); };
  var asset = function(p){ return p && !/^(https?:|data:|\/)/.test(p) ? BASE + p : p; };
  function getJSON(name, fallback, cb){
    fetch(BASE + name, {cache:'no-store'})
      .then(function(r){ if(!r.ok) throw 0; return r.json(); })
      .then(cb)
      .catch(function(){ cb(fallback); });
  }

  // ============================================================
  // 공통 머리글 / 바닥글  (메뉴를 바꿀 때는 NAV만 고치면 모든 페이지에 반영됩니다)
  // ============================================================
  var NAV = [
    ['about.html', '기관소개'],
    ['business.html', '사업·참여방법'],
    ['shop.html', '생산품'],
    ['news.html', '소식'],
    ['contact.html', '후원·오시는길']
  ];
  var page = (location.pathname.split('/').pop() || 'index.html');
  var links = function(cls){ return NAV.map(function(n){
    return '<a href="' + n[0] + '"' + (n[0] === page ? ' aria-current="page"' : '') + '>' + n[1] + '</a>'; }).join(''); };

  var h = $('siteHeader');
  if(h){
    h.outerHTML =
      '<header class="site-header"><div class="wrap nav">'
      + '<a class="brand" href="index.html" aria-label="참살이사회적협동조합 처음으로"><img src="assets/logo.png" alt="" width="32" height="33"><b translate="no">참살이사회적협동조합</b></a>'
      + '<nav class="menu" aria-label="주 메뉴">' + links() + '</nav>'
      + '<a class="btn btn-primary nav-cta" href="contact.html#donate">후원하기</a>'
      + '<button class="burger" id="burger" aria-label="메뉴 열기" aria-expanded="false" aria-controls="drawer"><span></span></button>'
      + '</div><nav class="drawer" id="drawer" aria-label="모바일 메뉴">' + links() + '<a href="contact.html#donate">후원하기</a></nav></header>';
  }
  var f = $('siteFooter');
  if(f){
    f.outerHTML =
      '<footer><div class="wrap"><div class="grid"><div><b>참살이사회적협동조합</b><address>'
      + '사업자등록번호 497-82-00175<br>대전광역시 대덕구 대전로 1032번길 51 (오정동), 3층<br>'
      + '전화 042-331-8012 / 070-7842-6070 · 팩스 070-7842-6099<br>'
      + '이메일 chamcoop2018@naver.com · 평일 08:00–17:30 (점심 12:00–13:00)</address></div>'
      + '<div class="links"><a href="https://www.daejeon.go.kr" target="_blank" rel="noopener">대전광역시</a>'
      + '<a href="https://www.daedeok.go.kr" target="_blank" rel="noopener">대덕구청</a>'
      + '<a href="http://www.ddsc.or.kr" target="_blank" rel="noopener">대덕구시니어클럽</a>'
      + '<a href="https://www.nts.go.kr" target="_blank" rel="noopener">국세청</a></div></div>'
      + '<p class="copy">© 참살이사회적협동조합. All rights reserved.</p></div></footer>';
  }

  // 모바일 메뉴: 링크 이동, 화면 확대/축소, Esc, 해시 변경 어느 경우에도 닫힘
  var burger = $('burger'), drawer = $('drawer');
  if(burger && drawer){
    var closeDrawer = function(){ drawer.classList.remove('open'); burger.setAttribute('aria-expanded', 'false'); };
    burger.addEventListener('click', function(){
      var open = drawer.classList.toggle('open');
      burger.setAttribute('aria-expanded', open);
    });
    drawer.addEventListener('click', function(e){ if(e.target.closest('a')) closeDrawer(); });
    window.addEventListener('hashchange', closeDrawer);
    window.addEventListener('resize', function(){ if(window.innerWidth > 1080) closeDrawer(); });
    document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeDrawer(); });
  }

  // ============================================================
  // 사업 (businesses.json): 홈의 일자리 유형, 사업 페이지의 사업 목록
  // ============================================================
  var homeUnits = $('homeUnits'), bizList = $('bizList'), statBiz = $('statBiz');
  if(homeUnits || bizList || statBiz){
    getJSON('businesses.json', [], function(list){
      list = list || [];
      var main = list.filter(function(b){ return b.status === 'live'; });
      var rest = list.filter(function(b){ return b.status !== 'live'; });
      var n = main.reduce(function(s, b){ return s + (b.units ? b.units.length : 0); }, 0);
      if(statBiz && n) statBiz.textContent = n + '개+';

      if(homeUnits){
        var units = [];
        main.forEach(function(b){ (b.units || []).forEach(function(u){ units.push(u); }); });
        homeUnits.innerHTML = units.map(function(u, i){
          var cnt = (u.programs || []).filter(function(p){ return p && p.title; }).length;
          return '<a class="cell" href="business.html"><span class="num">' + String(i + 1).padStart(2, '0') + '</span>'
            + '<b>' + esc(u.name) + '</b><span class="sub">' + (cnt ? '세부사업 ' + cnt + '개 운영' : '운영 정보 준비 중') + '</span></a>';
        }).join('') || '<p class="empty">등록된 사업이 없습니다.</p>';
      }

      if(bizList){
        var html = main.map(function(b){
          var us = (b.units || []).map(function(u){
            var progs = (u.programs || []).filter(function(p){ return p && p.title; });
            var inner = progs.length
              ? '<ul class="prog">' + progs.map(function(p){
                  return '<li>' + (p.image ? '<img src="' + esc(asset(p.image)) + '" alt="' + esc(p.title) + '" width="400" height="300" loading="lazy">' : '') + '<b>' + esc(p.title) + '</b>' + [p.period, p.slots ? p.slots + '명' : '', p.area].filter(Boolean).map(esc).join(' · ') + (p.content ? '<br>' + esc(p.content) : '') + '</li>';
                }).join('') + '</ul>'
              : '<p class="none">' + esc(u.detail || '사업단 운영 정보 준비 중') + '</p>';
            return '<div class="unit"><h3>' + esc(u.name) + '</h3>' + inner + '</div>';
          }).join('');
          return '<article class="biz-main"><div class="biz-top"><h2 class="biz-title">' + esc(b.title) + '</h2><span class="tag live">' + esc(b.statusLabel || '진행중') + '</span></div>'
            + '<p class="biz-desc">' + esc(b.desc) + '</p><div class="units">' + us + '</div></article>';
        }).join('');
        if(rest.length){
          html += '<div class="biz-more">' + rest.map(function(b){
            return '<article class="biz-card"><span class="tag">' + esc(b.statusLabel || '확대 예정') + '</span><h3>' + esc(b.title) + '</h3><p>' + esc(b.desc) + '</p>'
              + ((b.units || []).length ? '<ul>' + b.units.map(function(u){ return '<li>' + esc(u.name) + '</li>'; }).join('') + '</ul>' : '') + '</article>';
          }).join('') + '</div>';
        }
        bizList.innerHTML = html;
      }
    });
  }

  // ============================================================
  // 참여방법 (사업 페이지)
  // ============================================================
  var joinAcc = $('joinAcc');
  if(joinAcc){
    var JOIN = [
      { t:'노인공익활동사업 참여방법', c:['만 65세 이상 기초연금 수급자 중 심신 건강한 분','기초생활수급자 제외 (의료급여·교육급여·주거급여 수급자는 신청 가능)','건강보험 직장가입자 · 사업자등록증 보유자 제외','장기요양보험 1~5등급, 인지지원등급 판정자 제외'], h:['신분증, 주민등록등본(3개월 이내) 지참 후 방문 접수'] },
      { t:'노인역량활용사업 참여방법', c:['만 60세 이상 심신 건강한 분','기초수급자 · 건강보험 직장가입자 · 사업자등록증 보유자 제외','실업급여 수급 종료 후 90일 이후 참여 가능'], h:['신분증, 주민등록등본(3개월 이내) 지참 후 방문 접수 → 면접'] },
      { t:'공동체사업단 참여방법', c:['만 60세 이상 심신 건강한 분','기초수급자 · 건강보험 직장가입자 · 사업자등록증 보유자 제외','사업 특성에 적합한 분','장기요양보험 1~5등급, 인지지원등급 판정자 제외'], h:['신분증, 주민등록등본(3개월 이내) 지참 후 방문 접수 → 면접'] },
      { t:'취업지원사업 참여방법', c:['만 60세 이상 심신 건강한 분','기초수급자 · 건강보험 직장가입자 · 사업자등록증 보유자 제외','사업 특성에 적합한 분'], h:['신분증, 주민등록등본(3개월 이내) 지참 후 방문 접수 → 면접'] }
    ];
    var li = function(a){ return a.map(function(x){ return '<li>' + esc(x) + '</li>'; }).join(''); };
    joinAcc.innerHTML = JOIN.map(function(j){
      return '<details><summary>' + esc(j.t) + '</summary><div class="body"><div><h4>참여조건 및 제외대상</h4><ul>' + li(j.c) + '</ul></div><div><h4>신청방법</h4><ul>' + li(j.h) + '</ul></div></div></details>';
    }).join('');
  }

  // ============================================================
  // 설정(카카오톡) + 생산품 + 주문 담기
  // ============================================================
  var kakao = '';
  var kakaoBtns = ['kakaoBtn', 'orderKakao'].map($).filter(Boolean);
  if(kakaoBtns.length){
    getJSON('config.json', {}, function(c){
      kakao = (c && c.kakaoChannelUrl) || '';
      if(kakao) kakaoBtns.forEach(function(el){ el.href = kakao; el.hidden = false; });
    });
  }

  var IMG = { p1:'files/products/blueberry_fresh.jpg', p2:'files/products/blueberry_bag.jpg', p3:'files/products/jam_set.jpg', p4:'files/products/dasulgi.jpg' };
  var cart = {}, PRODUCTS = [];
  var priceOf = function(p){ return (p.price == null) ? '가격 문의' : new Intl.NumberFormat('ko-KR').format(p.price) + '원 / ' + esc(p.unit); };
  function updateBar(){
    var bar = $('orderBar'); if(!bar) return;
    var ids = Object.keys(cart).filter(function(k){ return cart[k] > 0; });
    var count = ids.reduce(function(a, k){ return a + cart[k]; }, 0);
    $('orderCount').textContent = count;
    bar.classList.toggle('on', count > 0);
    var lines = ids.map(function(id){ var p = PRODUCTS.filter(function(x){ return x.id === id; })[0]; return '- ' + (p ? plain(p.name) : id) + ' x ' + cart[id]; });
    var body = '아래 상품 주문을 요청합니다.\r\n\r\n' + lines.join('\r\n') + '\r\n\r\n이름:\r\n연락처:\r\n수령방법(방문/택배):';
    $('orderMail').href = 'mailto:chamcoop2018@naver.com?subject=' + encodeURIComponent('[생산품 주문요청]') + '&body=' + encodeURIComponent(body);
  }
  var prodGrid = $('prodGrid'), homeProds = $('homeProds');
  if(prodGrid || homeProds){
    getJSON('products.json', [], function(list){
      PRODUCTS = list || [];
      var card = function(p, withCart){
        var src = asset(p.img || IMG[p.id]);
        return '<article class="prod">' + (src ? '<img src="' + esc(src) + '" alt="' + esc(p.name) + '" width="400" height="300" loading="lazy">' : '')
          + '<div class="pb"><small>' + esc(p.season) + '</small><h3>' + esc(p.name) + '</h3>' + (withCart ? '<p class="desc">' + esc(p.desc) + '</p>' : '')
          + '<div class="price"><b>' + priceOf(p) + '</b>' + (withCart ? '<button class="add" data-id="' + esc(p.id) + '">담기</button>' : '') + '</div></div></article>';
      };
      if(prodGrid) prodGrid.innerHTML = PRODUCTS.map(function(p){ return card(p, true); }).join('') || '<p class="empty">등록된 상품이 없습니다.</p>';
      if(homeProds) homeProds.innerHTML = PRODUCTS.slice(0, 4).map(function(p){ return card(p, false); }).join('');
    });
  }
  if(prodGrid){
    prodGrid.addEventListener('click', function(e){
      var b = e.target.closest('.add'); if(!b) return;
      var id = b.getAttribute('data-id');
      cart[id] = (cart[id] || 0) + 1;
      b.classList.add('on'); b.textContent = '담김 ' + cart[id];
      updateBar();
    });
  }

  // ============================================================
  // 공지사항 (소식 페이지: 아코디언 / 홈: 최신 3건)
  // ============================================================
  var noticeList = $('noticeList'), homeNotices = $('homeNotices');
  if(noticeList || homeNotices){
    getJSON('notices.json', [], function(list){
      list = list || [];
      if(homeNotices){
        homeNotices.innerHTML = list.slice(0, 3).map(function(n){
          return '<a class="row" href="news.html#notice"><span class="tag">' + esc(n.tag) + '</span><span class="nt">' + esc(n.title) + '</span><span class="nd">' + esc(n.date) + '</span></a>';
        }).join('') || '<p class="empty">등록된 공지사항이 없습니다.</p>';
      }
      if(noticeList){
        if(!list.length){ noticeList.innerHTML = '<p class="empty">등록된 공지사항이 없습니다.</p>'; return; }
        var SHOW = 8;
        noticeList.innerHTML = list.map(function(n, i){
          var att = (n.attachments || []).map(function(a){ return '<a href="' + esc(asset(a.url)) + '" target="_blank" rel="noopener" download>' + esc(a.name) + '</a>'; }).join('');
          return '<details' + (i >= SHOW ? ' hidden data-more' : '') + '><summary><span class="tag">' + esc(n.tag) + '</span><span class="nt">' + esc(n.title) + '</span><span class="nd">' + esc(n.date) + '</span></summary>'
            + '<div class="nbody">' + esc(n.body) + (att ? '<div class="att">' + att + '</div>' : '') + '</div></details>';
        }).join('');
        if(list.length > SHOW){
          var btn = document.createElement('button');
          btn.className = 'btn btn-outline more'; btn.type = 'button';
          btn.textContent = '지난 공지 더 보기 (' + (list.length - SHOW) + ')';
          btn.onclick = function(){ noticeList.querySelectorAll('[data-more]').forEach(function(d){ d.hidden = false; }); btn.remove(); };
          noticeList.parentNode.insertBefore(btn, noticeList.nextSibling);
        }
      }
    });
  }

  // ============================================================
  // 보도자료
  // ============================================================
  var pressList = $('pressList');
  if(pressList){
    getJSON('press.json', [], function(list){
      if(!list || !list.length){ pressList.innerHTML = '<p class="empty">등록된 보도자료가 없습니다.</p>'; return; }
      pressList.innerHTML = list.map(function(p){
        var img = p.img ? '<img src="' + esc(asset(p.img)) + '" alt="" width="140" height="96" loading="lazy" onerror="this.style.display=\'none\'">' : '<span></span>';
        var src = p.url ? '<a class="src" href="' + esc(p.url) + '" target="_blank" rel="noopener">' + esc(p.source || '출처 보기') + ' ↗</a>' : '';
        return '<article class="press-card">' + img + '<div><span class="tag">' + esc(p.tag || '소식') + '</span><h3>' + esc(p.title) + '</h3><p>' + esc(plain(p.body)) + '</p>' + src + '</div></article>';
      }).join('');
    });
  }

  // ============================================================
  // 활동 사진 + 라이트박스
  // ============================================================
  var photoList = $('photoList'), lb = $('lb');
  if(photoList && lb){
    var PHOTOS = [], idx = [0, 0];
    var showLb = function(){
      var p = PHOTOS[idx[0]], im = p.images;
      $('lbImg').src = asset(im[idx[1]]); $('lbImg').alt = plain(p.title);
      $('lbCap').textContent = plain(p.title) + (im.length > 1 ? ' (' + (idx[1] + 1) + '/' + im.length + ')' : '');
    };
    var step = function(d){ var n = PHOTOS[idx[0]].images.length; idx[1] = (idx[1] + d + n) % n; showLb(); };
    $('lbX').onclick = function(){ lb.classList.remove('on'); };
    $('lbP').onclick = function(){ step(-1); };
    $('lbN').onclick = function(){ step(1); };
    lb.addEventListener('click', function(e){ if(e.target === lb) lb.classList.remove('on'); });
    document.addEventListener('keydown', function(e){
      if(!lb.classList.contains('on')) return;
      if(e.key === 'Escape') lb.classList.remove('on');
      if(e.key === 'ArrowLeft') step(-1);
      if(e.key === 'ArrowRight') step(1);
    });
    getJSON('photos.json', [], function(list){
      PHOTOS = (list || []).filter(function(p){ return p.images && p.images.length; });
      if(!PHOTOS.length){ photoList.outerHTML = '<p class="empty">등록된 사진이 없습니다. 활동 사진이 올라오면 이 자리에 표시됩니다.</p>'; return; }
      photoList.innerHTML = PHOTOS.map(function(p, i){
        return '<button class="photo-card" data-i="' + i + '"><div class="th"><img src="' + esc(asset(p.images[0])) + '" alt="" width="400" height="300" loading="lazy"></div><span class="pt">' + esc(p.title) + '</span><small>' + esc(p.date || '') + '</small></button>';
      }).join('');
      photoList.addEventListener('click', function(e){
        var c = e.target.closest('.photo-card'); if(!c) return;
        idx = [+c.getAttribute('data-i'), 0]; showLb(); lb.classList.add('on'); $('lbX').focus();
      });
    });
  }
})();
