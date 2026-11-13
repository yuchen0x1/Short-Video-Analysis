package com.yuchen.qushuiyin;

import static org.junit.Assert.assertFalse;
import static org.junit.Assert.assertNull;
import static org.junit.Assert.assertTrue;

import java.net.InetAddress;
import java.net.URI;

import org.junit.Test;

/** JVM tests for the media proxy security policy. */
public class MediaProxySecurityTest {

    @Test
    public void mediaProxyAcceptsConfiguredDomainAndSubdomain() {
        assertTrue(MediaProxySecurity.isAllowedMediaUrl("https://douyinvod.com/video.mp4"));
        assertTrue(MediaProxySecurity.isAllowedMediaUrl("https://cdn.douyinvod.com/video.mp4"));
        assertTrue(MediaProxySecurity.isAllowedMediaUrl("https://a.b.mcdn.bilivideo.cn/video.mp4"));
        assertTrue(MediaProxySecurity.isAllowedMediaUrl("https://i2.hdslb.com/bfs/archive/cover.jpg"));
    }

    @Test
    public void mediaProxyRejectsUntrustedHostsCredentialsAndPorts() {
        assertFalse(MediaProxySecurity.isAllowedMediaUrl("https://douyinvod.com.attacker.example/video.mp4"));
        assertFalse(MediaProxySecurity.isAllowedMediaUrl("https://localhost/video.mp4"));
        assertFalse(MediaProxySecurity.isAllowedMediaUrl("https://media.localhost/video.mp4"));
        assertTrue(MediaProxySecurity.isAllowedMediaUrl("https://localhost.douyinvod.com/video.mp4"));
        assertFalse(MediaProxySecurity.isAllowedMediaUrl("https://user:password@douyinvod.com/video.mp4"));
        assertFalse(MediaProxySecurity.isAllowedMediaUrl("https://douyinvod.com:8443/video.mp4"));
        assertFalse(MediaProxySecurity.isAllowedMediaUrl("http://douyinvod.com:443/video.mp4"));
    }

    @Test
    public void mediaProxyRejectsPrivateLoopbackLinkLocalAndReservedAddresses() throws Exception {
        assertFalse(MediaProxySecurity.arePublicAddresses(new InetAddress[]{InetAddress.getByName("127.0.0.1")}));
        assertFalse(MediaProxySecurity.arePublicAddresses(new InetAddress[]{InetAddress.getByName("10.0.0.1")}));
        assertFalse(MediaProxySecurity.arePublicAddresses(new InetAddress[]{InetAddress.getByName("169.254.1.1")}));
        assertFalse(MediaProxySecurity.arePublicAddresses(new InetAddress[]{InetAddress.getByName("100.64.0.1")}));
        assertFalse(MediaProxySecurity.arePublicAddresses(new InetAddress[]{InetAddress.getByName("192.0.2.1")}));
        assertFalse(MediaProxySecurity.arePublicAddresses(new InetAddress[]{InetAddress.getByName("::1")}));
        assertTrue(MediaProxySecurity.arePublicAddresses(new InetAddress[]{InetAddress.getByName("8.8.8.8")}));
    }

    @Test
    public void mediaProxyFailsClosedWhenAnyDnsAddressIsForbidden() throws Exception {
        assertFalse(MediaProxySecurity.isAllowedMediaUrl(
                "https://cdn.douyinvod.com/video.mp4",
                new InetAddress[]{InetAddress.getByName("8.8.8.8"), InetAddress.getByName("192.168.1.10")}));
        assertTrue(MediaProxySecurity.isAllowedMediaUrl(
                "https://cdn.douyinvod.com/video.mp4",
                new InetAddress[]{InetAddress.getByName("8.8.8.8")}));
    }

    @Test
    public void mediaProxyResolvesOnlySafeRedirects() {
        URI base = URI.create("https://cdn.douyinvod.com/path/video.mp4");
        assertTrue(MediaProxySecurity.isAllowedMediaUrl(
                MediaProxySecurity.resolveRedirect(base, "../next.mp4").toString()));
        assertNull(MediaProxySecurity.resolveRedirect(base, "https://attacker.example/next.mp4"));
        assertNull(MediaProxySecurity.resolveRedirect(base, "http://user:password@douyinvod.com/next.mp4"));
    }

    @Test
    public void mediaProxyEnforcesPreflightLengthAndUnknownStreamingLength() {
        assertTrue(MediaProxySecurity.isResponseLengthAllowed(524288000L, 524288000L));
        assertFalse(MediaProxySecurity.isResponseLengthAllowed(524288001L, 524288000L));
        assertTrue(MediaProxySecurity.isResponseLengthAllowed(-1L, 524288000L));
        assertTrue(MediaProxySecurity.isResponseLengthAllowed(
                MediaProxySecurity.parseContentLength("524288000"), 524288000L));
        assertFalse(MediaProxySecurity.isResponseLengthAllowed(
                MediaProxySecurity.parseContentLength("524288001"), 524288000L));
        assertNull(MediaProxySecurity.parseContentLength("chunked"));
        assertNull(MediaProxySecurity.parseContentLength("-1"));
        assertFalse(MediaProxySecurity.isContentLengthHeaderValid("not-a-number"));
        assertFalse(MediaProxySecurity.isContentLengthHeaderValid("9223372036854775808"));
    }

    @Test
    public void mediaProxyHandlesRedirectStatusLimit() {
        assertTrue(MediaProxyPlugin.isRedirectStatus(301));
        assertTrue(MediaProxyPlugin.isRedirectStatus(307));
        assertFalse(MediaProxyPlugin.isRedirectStatus(200));
        assertFalse(MediaProxyPlugin.isRedirectStatus(304));
    }

    @Test
    public void downloadRequestsStorageOnlyOnAndroidNineAndBelow() {
        assertTrue(DownloadPermissionSecurity.shouldRequestStoragePermission(28, false));
        assertTrue(DownloadPermissionSecurity.shouldRequestStoragePermission(23, false));
        assertFalse(DownloadPermissionSecurity.shouldRequestStoragePermission(28, true));
        assertFalse(DownloadPermissionSecurity.shouldRequestStoragePermission(29, false));
    }
}
